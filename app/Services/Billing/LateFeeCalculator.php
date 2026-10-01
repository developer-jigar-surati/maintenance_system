<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\LateFeeRule;
use App\Models\Society;
use App\Services\Accounting\LedgerPoster;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Accrues interest and penalties on overdue bills.
 *
 * Two properties matter here more than anything else. Accrual is idempotent --
 * a line carries an accrual_key unique per invoice, so re-running a month
 * cannot charge a resident twice. And it is additive -- each run posts only
 * the period's own interest, never a recomputed running total.
 */
class LateFeeCalculator
{
    public function __construct(private LedgerPoster $ledger) {}

    /**
     * Posts any interest owed on one invoice as at $on.
     *
     * @return InvoiceLine|null the line created, or null if nothing was due
     */
    public function accrue(Invoice $invoice, LateFeeRule $rule, ?Carbon $on = null): ?InvoiceLine
    {
        $on ??= now();

        if (! $rule->appliesTo($invoice)) {
            return null;
        }

        $from = $rule->chargeableFrom($invoice);

        if ($on->lt($from)) {
            return null;
        }

        $periodKey = $this->periodKey($rule, $on);
        $accrualKey = "late_fee:{$periodKey}";

        // Cheap pre-check; the unique index below is the real guarantee.
        if ($invoice->lines()->where('accrual_key', $accrualKey)->exists()) {
            return null;
        }

        $amount = $this->amountFor($invoice, $rule, $from, $on);

        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $rule, $amount, $periodKey, $accrualKey, $on) {
            try {
                $line = (new InvoiceLine([
                    'charge_head_id' => $rule->charge_head_id,
                    'description' => $this->describe($rule, $periodKey),
                    'basis' => 'manual',
                    'quantity' => 1,
                    'rate' => $amount,
                    'tax_rate' => 0,
                    'source' => 'late_fee',
                    'period_key' => $periodKey,
                    'accrual_key' => $accrualKey,
                    'sort_order' => 999,
                    'meta' => ['rule_id' => $rule->id, 'method' => $rule->method, 'rate' => (float) $rule->rate],
                ]))->computeTotals();

                $invoice->lines()->save($line);
            } catch (QueryException $e) {
                // A concurrent run won the race; its line is equally valid.
                if ($this->isUniqueViolation($e)) {
                    return null;
                }

                throw $e;
            }

            $invoice->load('lines')->recalculate(save: false);
            $invoice->last_accrued_at = $on;
            $invoice->accrual_period_key = $periodKey;
            $invoice->save();

            // Interest is income the society has earned, so it belongs in the
            // books as its own entry rather than only on the bill.
            $this->postToLedger($invoice, $rule, $line, $on);

            return $line;
        });
    }

    /** Dr Members receivable / Cr Late payment interest. */
    private function postToLedger(Invoice $invoice, LateFeeRule $rule, InvoiceLine $line, Carbon $on): void
    {
        $society = $invoice->resolveSociety();
        $amount = (float) $line->line_total;

        $rule->loadMissing('chargeHead.ledgerAccount');

        $incomeAccount = $rule->chargeHead?->ledgerAccount
            ?? $this->ledger->account($society, LedgerPoster::INTEREST_INCOME);

        $this->ledger->post(
            $society,
            'invoice',
            $on,
            "Late payment interest on {$invoice->invoice_number}",
            [
                [
                    'account' => $this->ledger->account($society, LedgerPoster::MEMBERS_RECEIVABLE),
                    'debit' => $amount,
                    'credit' => 0,
                    'unit_id' => $invoice->unit_id,
                    'memo' => $line->description,
                ],
                [
                    'account' => $incomeAccount,
                    'debit' => 0,
                    'credit' => $amount,
                    'unit_id' => $invoice->unit_id,
                    'memo' => $line->description,
                ],
            ],
            $invoice,
        );
    }

    /**
     * Runs accrual across every open overdue invoice in a society.
     *
     * @return array{posted: int, amount: float}
     */
    public function accrueForSociety(Society $society, LateFeeRule $rule, ?Carbon $on = null): array
    {
        $on ??= now();
        $posted = 0;
        $amount = 0.0;

        Invoice::query()
            ->where('society_id', $society->id)
            ->open()
            ->whereDate('due_date', '<', $on->toDateString())
            ->with('lines')
            ->chunkById(200, function (Collection $invoices) use ($rule, $on, &$posted, &$amount) {
                foreach ($invoices as $invoice) {
                    $line = $this->accrue($invoice, $rule, $on);

                    if ($line !== null) {
                        $posted++;
                        $amount += (float) $line->line_total;
                    }
                }
            });

        return ['posted' => $posted, 'amount' => round($amount, 2)];
    }

    /**
     * Interest for the current period only.
     *
     * Simple interest is charged on the original overdue principal; compound
     * interest is charged on the balance including interest already posted.
     */
    public function amountFor(Invoice $invoice, LateFeeRule $rule, Carbon $from, Carbon $on): float
    {
        $balance = (float) $invoice->balance;

        $principal = $rule->compounding === 'compound'
            ? $balance
            : max(0.0, $balance - (float) $invoice->late_fee_total);

        if ($principal <= 0) {
            return 0.0;
        }

        $daysOverdue = max(0, (int) $from->diffInDays($on));

        $raw = match ($rule->method) {
            'flat' => (float) $rule->rate,
            'percent_per_month' => $principal * (float) $rule->rate / 100
                * $this->periodFraction($rule, $daysOverdue),
            'percent_per_annum' => $principal * (float) $rule->rate / 100
                * $this->periodFraction($rule, $daysOverdue) / 12,
            'slab' => $this->slabAmount($rule, $principal, $daysOverdue),
            default => 0.0,
        };

        return $this->clamp($rule, round($raw, 2));
    }

    /**
     * How much of a month this accrual covers. Monthly accrual charges a whole
     * month at a time; daily accrual charges a single day's share.
     */
    private function periodFraction(LateFeeRule $rule, int $daysOverdue): float
    {
        return $rule->accrual_frequency === 'daily' ? 1 / 30 : 1.0;
    }

    /** Tiered penalties, e.g. 100 up to 30 days, 250 up to 60, 500 beyond. */
    private function slabAmount(LateFeeRule $rule, float $principal, int $daysOverdue): float
    {
        $matched = 0.0;

        foreach ($rule->slabs ?? [] as $slab) {
            $upTo = $slab['up_to_days'] ?? null;

            if ($upTo === null || $daysOverdue <= (int) $upTo) {
                $matched = ($slab['type'] ?? 'flat') === 'percent'
                    ? $principal * (float) ($slab['value'] ?? 0) / 100
                    : (float) ($slab['value'] ?? 0);
                break;
            }

            // Past every defined tier: the last one keeps applying.
            $matched = ($slab['type'] ?? 'flat') === 'percent'
                ? $principal * (float) ($slab['value'] ?? 0) / 100
                : (float) ($slab['value'] ?? 0);
        }

        return $matched;
    }

    private function clamp(LateFeeRule $rule, float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $amount = max($amount, (float) $rule->minimum_amount);

        if ($rule->maximum_amount !== null) {
            $amount = min($amount, (float) $rule->maximum_amount);
        }

        return round($amount, 2);
    }

    private function periodKey(LateFeeRule $rule, Carbon $on): string
    {
        return $rule->accrual_frequency === 'daily'
            ? $on->format('Y-m-d')
            : $on->format('Y-m');
    }

    private function describe(LateFeeRule $rule, string $periodKey): string
    {
        return sprintf('%s (%s)', $rule->name ?: 'Late payment interest', $periodKey);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true);
    }
}
