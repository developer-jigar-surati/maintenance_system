<?php

namespace App\Services\Billing;

use App\Models\BillingPlan;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Society;
use App\Models\Unit;
use App\Services\Accounting\LedgerPoster;
use App\Services\NumberGenerator;
use App\Support\SocietyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a billing plan into invoices.
 *
 * A run is idempotent per unit and period: re-running the same month will not
 * produce a second bill, which matters because bill runs get triggered by both
 * the scheduler and impatient committee members.
 */
class InvoiceGenerator
{
    public function __construct(
        private ChargeCalculator $charges,
        private NumberGenerator $numbers,
        private LedgerPoster $ledger,
        private SocietyContext $context,
    ) {}

    /**
     * Generates bills for every eligible unit under a plan.
     *
     * @return array{created: int, skipped: int, total: float, invoices: Collection<int, Invoice>}
     */
    public function run(BillingPlan $plan, ?Carbon $on = null, ?int $generatedBy = null): array
    {
        $on ??= now();
        [$periodStart, $periodEnd] = $plan->periodFor($on);

        $society = $plan->society;
        $units = $this->eligibleUnits($plan);

        $created = new Collection;
        $skipped = 0;

        foreach ($units as $unit) {
            if ($this->alreadyBilled($plan, $unit, $periodStart)) {
                $skipped++;

                continue;
            }

            $invoice = $this->generateFor($plan, $unit, $periodStart, $periodEnd, $on, $generatedBy);

            if ($invoice === null) {
                $skipped++;

                continue;
            }

            $created->push($invoice);
        }

        $plan->advanceSchedule($periodStart);

        return [
            'created' => $created->count(),
            'skipped' => $skipped,
            'total' => round((float) $created->sum('total'), 2),
            'invoices' => $created,
        ];
    }

    /**
     * Builds one invoice. Returns null when the plan produces no chargeable
     * lines for this unit, rather than issuing an empty bill.
     */
    public function generateFor(
        BillingPlan $plan,
        Unit $unit,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?Carbon $issueDate = null,
        ?int $generatedBy = null,
    ): ?Invoice {
        $issueDate ??= now();
        $society = $plan->society;

        return DB::transaction(function () use ($plan, $unit, $periodStart, $periodEnd, $issueDate, $generatedBy, $society) {
            $lines = $this->buildLines($plan, $unit, $issueDate);

            if ($lines->isEmpty()) {
                return null;
            }

            $invoice = new Invoice([
                'society_id' => $society->id,
                'unit_id' => $unit->id,
                'billing_plan_id' => $plan->id,
                'financial_year_id' => $society->currentFinancialYear()?->id,
                'invoice_number' => $this->numbers->next(NumberGenerator::INVOICE, $society, $issueDate),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $issueDate->copy()->addDays((int) $plan->due_after_days)->toDateString(),
                // Shown for context on the bill; not re-charged, since the
                // earlier invoices remain open in their own right.
                'arrears_amount' => $this->arrearsFor($unit),
                'status' => $plan->auto_issue ? 'issued' : 'draft',
                'issued_at' => $plan->auto_issue ? $issueDate : null,
                'notes' => $plan->invoice_notes,
                'generated_by' => $generatedBy,
            ]);

            $invoice->save();

            foreach ($lines as $index => $line) {
                $invoice->lines()->save(
                    (new InvoiceLine([
                        'charge_head_id' => $line['charge_head_id'],
                        'description' => $line['description'],
                        'basis' => $line['basis'],
                        'quantity' => $line['quantity'],
                        'rate' => $line['rate'],
                        'tax_rate' => $line['tax_rate'],
                        'source' => 'charge',
                        'period_key' => $periodStart->format('Y-m'),
                        'sort_order' => $index,
                    ]))->computeTotals()
                );
            }

            $invoice->load('lines')->recalculate();

            if ($invoice->status !== 'draft') {
                $this->ledger->postInvoice($invoice);
            }

            return $invoice;
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function buildLines(BillingPlan $plan, Unit $unit, Carbon $on): Collection
    {
        return $plan->chargeHeads
            ->map(function ($head) use ($unit, $plan, $on) {
                $charge = $this->charges->for($unit, $head, $plan, $on);

                return $charge === null ? null : $charge + ['charge_head_id' => $head->id];
            })
            ->filter()
            ->values();
    }

    private function eligibleUnits(BillingPlan $plan): Collection
    {
        return Unit::query()
            ->where('society_id', $plan->society_id)
            ->billable()
            ->with(['block', 'activeResidents', 'society'])
            ->get();
    }

    /** Guards against billing the same unit twice for one period. */
    private function alreadyBilled(BillingPlan $plan, Unit $unit, Carbon $periodStart): bool
    {
        return Invoice::query()
            ->where('society_id', $plan->society_id)
            ->where('unit_id', $unit->id)
            ->where('billing_plan_id', $plan->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereNull('deleted_at')
            ->exists();
    }

    private function arrearsFor(Unit $unit): float
    {
        return round((float) Invoice::query()
            ->where('unit_id', $unit->id)
            ->open()
            ->sum('balance'), 2);
    }

    /** Plans whose next run has come due, for the scheduled command. */
    public function duePlans(?Society $society = null, ?Carbon $on = null): Collection
    {
        $on ??= now();

        return BillingPlan::query()
            ->when($society, fn ($q) => $q->where('society_id', $society->id))
            ->when(! $society, fn ($q) => $q->withoutGlobalScopes())
            ->active()
            ->where('auto_generate', true)
            ->whereNotNull('next_run_on')
            ->whereDate('next_run_on', '<=', $on->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $on->toDateString()))
            ->with(['chargeHeads', 'society'])
            ->get();
    }
}
