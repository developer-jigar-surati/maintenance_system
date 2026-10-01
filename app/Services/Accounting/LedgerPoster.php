<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Society;
use App\Services\NumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes the double-entry side of business documents.
 *
 * Invoices, payments and expenses each post a balanced journal entry, which is
 * what lets the reports (trial balance, income and expenditure, balance sheet)
 * be derived rather than guessed at.
 */
class LedgerPoster
{
    /** System account codes, seeded for every society. */
    public const MEMBERS_RECEIVABLE = '1200';

    public const CASH_IN_HAND = '1010';

    public const BANK = '1020';

    public const MAINTENANCE_INCOME = '4000';

    public const INTEREST_INCOME = '4900';

    public const ADVANCE_FROM_MEMBERS = '2100';

    public const SUNDRY_CREDITORS = '2000';

    public const GST_PAYABLE = '2200';

    public const TDS_PAYABLE = '2300';

    public const GENERAL_EXPENSE = '5000';

    public function __construct(private NumberGenerator $numbers) {}

    /**
     * Raising a bill creates a receivable and recognises the income.
     *
     * Dr Members receivable / Cr income heads (+ Cr GST payable when taxed).
     */
    public function postInvoice(Invoice $invoice): ?JournalEntry
    {
        if ((float) $invoice->total <= 0) {
            return null;
        }

        $society = $invoice->resolveSociety();

        // Each line's income account is read below; load them in one query
        // rather than one per line.
        $invoice->loadMissing('lines.chargeHead.ledgerAccount');

        $lines = [];

        $lines[] = [
            'account' => $this->account($society, self::MEMBERS_RECEIVABLE),
            'debit' => (float) $invoice->total,
            'credit' => 0,
            'unit_id' => $invoice->unit_id,
            'memo' => "Invoice {$invoice->invoice_number}",
        ];

        // Credit each charge head's own income account so the income and
        // expenditure statement breaks down by head, not by one lump.
        foreach ($invoice->lines as $line) {
            $account = $line->chargeHead?->ledgerAccount
                ?? $this->account($society, $line->isLateFee() ? self::INTEREST_INCOME : self::MAINTENANCE_INCOME);

            $lines[] = [
                'account' => $account,
                'debit' => 0,
                'credit' => (float) $line->amount,
                'unit_id' => $invoice->unit_id,
                'memo' => $line->description,
            ];

            if ((float) $line->tax_amount > 0) {
                $lines[] = [
                    'account' => $this->account($society, self::GST_PAYABLE),
                    'debit' => 0,
                    'credit' => (float) $line->tax_amount,
                    'unit_id' => $invoice->unit_id,
                    'memo' => "Tax on {$line->description}",
                ];
            }
        }

        return $this->post(
            $society,
            'invoice',
            $invoice->issue_date,
            "Invoice {$invoice->invoice_number}",
            $lines,
            $invoice,
        );
    }

    /**
     * Receiving money clears the receivable, or parks the surplus as an
     * advance when it exceeds what the unit currently owes.
     *
     * Dr Cash/Bank / Cr Members receivable (+ Cr Advance for any excess).
     */
    public function postPayment(Payment $payment): ?JournalEntry
    {
        if ((float) $payment->amount <= 0) {
            return null;
        }

        $society = $payment->resolveSociety();

        $allocated = round((float) $payment->amount - (float) $payment->unallocated_amount, 2);
        $advance = round((float) $payment->unallocated_amount, 2);

        $lines = [[
            'account' => $this->account($society, $this->receiptAccountCode($payment)),
            'debit' => (float) $payment->amount,
            'credit' => 0,
            'unit_id' => $payment->unit_id,
            'memo' => "Payment {$payment->payment_number}",
        ]];

        if ($allocated > 0) {
            $lines[] = [
                'account' => $this->account($society, self::MEMBERS_RECEIVABLE),
                'debit' => 0,
                'credit' => $allocated,
                'unit_id' => $payment->unit_id,
                'memo' => 'Against outstanding dues',
            ];
        }

        if ($advance > 0) {
            $lines[] = [
                'account' => $this->account($society, self::ADVANCE_FROM_MEMBERS),
                'debit' => 0,
                'credit' => $advance,
                'unit_id' => $payment->unit_id,
                'memo' => 'Advance received',
            ];
        }

        return $this->post(
            $society,
            'payment',
            $payment->paid_at,
            "Receipt against payment {$payment->payment_number}",
            $lines,
            $payment,
        );
    }

    /**
     * Approving a vendor bill recognises the expense and the liability.
     *
     * Dr Expense head / Cr Sundry creditors (+ Cr TDS payable when withheld).
     */
    public function postExpense(Expense $expense): ?JournalEntry
    {
        if ((float) $expense->total <= 0) {
            return null;
        }

        $society = $expense->resolveSociety();

        $expenseAccount = $expense->ledgerAccount
            ?? $expense->chargeHead?->ledgerAccount
            ?? $this->account($society, self::GENERAL_EXPENSE);

        $lines = [[
            'account' => $expenseAccount,
            'debit' => (float) $expense->total,
            'credit' => 0,
            'memo' => $expense->description ?: "Expense {$expense->expense_number}",
        ]];

        $tds = (float) $expense->tds_amount;

        if ($tds > 0) {
            $lines[] = [
                'account' => $this->account($society, self::TDS_PAYABLE),
                'debit' => 0,
                'credit' => $tds,
                'memo' => 'TDS withheld',
            ];
        }

        $lines[] = [
            'account' => $this->account($society, self::SUNDRY_CREDITORS),
            'debit' => 0,
            'credit' => round((float) $expense->total - $tds, 2),
            'memo' => $expense->vendor?->name ?? 'Creditor',
        ];

        return $this->post(
            $society,
            'expense',
            $expense->bill_date,
            "Expense {$expense->expense_number}",
            $lines,
            $expense,
        );
    }

    /** Paying a vendor settles the liability. Dr Creditors / Cr Bank. */
    public function postExpensePayment(ExpensePayment $payment): ?JournalEntry
    {
        if ((float) $payment->amount <= 0) {
            return null;
        }

        $society = $payment->resolveSociety();

        $lines = [
            [
                'account' => $this->account($society, self::SUNDRY_CREDITORS),
                'debit' => (float) $payment->amount,
                'credit' => 0,
                'memo' => "Voucher {$payment->voucher_number}",
            ],
            [
                'account' => $payment->bankAccount?->ledgerAccount
                    ?? $this->account($society, $payment->method === 'cash' ? self::CASH_IN_HAND : self::BANK),
                'debit' => 0,
                'credit' => (float) $payment->amount,
                'memo' => "Voucher {$payment->voucher_number}",
            ],
        ];

        return $this->post(
            $society,
            'expense_payment',
            $payment->paid_on,
            "Payment voucher {$payment->voucher_number}",
            $lines,
            $payment,
        );
    }

    /**
     * Writes a balanced journal entry. Zero-value lines are dropped, and the
     * entry is rejected outright if debits and credits disagree -- a silent
     * imbalance would corrupt every report downstream.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function post(
        Society $society,
        string $type,
        Carbon|string $date,
        string $narration,
        array $lines,
        ?object $source = null,
        ?int $createdBy = null,
    ): JournalEntry {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        $lines = array_values(array_filter(
            $lines,
            fn ($l) => round((float) $l['debit'], 2) > 0 || round((float) $l['credit'], 2) > 0
        ));

        $debit = round(array_sum(array_map(fn ($l) => (float) $l['debit'], $lines)), 2);
        $credit = round(array_sum(array_map(fn ($l) => (float) $l['credit'], $lines)), 2);

        if (abs($debit - $credit) >= 0.005) {
            throw new \DomainException(sprintf(
                'Refusing to post an unbalanced entry for %s: debits %.2f, credits %.2f.',
                $narration, $debit, $credit
            ));
        }

        return DB::transaction(function () use ($society, $type, $date, $narration, $lines, $source, $createdBy, $debit, $credit) {
            $entry = JournalEntry::create([
                'society_id' => $society->id,
                'financial_year_id' => $society->currentFinancialYear()?->id,
                'entry_number' => $this->numbers->next(NumberGenerator::JOURNAL, $society, $date),
                'entry_date' => $date->toDateString(),
                'type' => $type,
                'narration' => $narration,
                'source_type' => $source ? $source::class : null,
                'source_id' => $source?->getKey(),
                'total_debit' => $debit,
                'total_credit' => $credit,
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'society_id' => $society->id,
                    'ledger_account_id' => $line['account'] instanceof LedgerAccount
                        ? $line['account']->id
                        : $line['account'],
                    'unit_id' => $line['unit_id'] ?? null,
                    'debit' => round((float) $line['debit'], 2),
                    'credit' => round((float) $line['credit'], 2),
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    /** Reverses an entry by posting its mirror image, keeping both on record. */
    public function reverse(JournalEntry $entry, ?string $reason = null): JournalEntry
    {
        $society = $entry->resolveSociety();

        $lines = $entry->lines->map(fn ($l) => [
            'account' => $l->ledger_account_id,
            'debit' => (float) $l->credit,
            'credit' => (float) $l->debit,
            'unit_id' => $l->unit_id,
            'memo' => $l->memo,
        ])->all();

        $reversal = $this->post(
            $society,
            $entry->type,
            now(),
            $reason ?: "Reversal of {$entry->entry_number}",
            $lines,
        );

        $entry->forceFill(['reversed_by_entry_id' => $reversal->id])->save();

        return $reversal;
    }

    private function receiptAccountCode(Payment $payment): string
    {
        return $payment->method === 'cash' ? self::CASH_IN_HAND : self::BANK;
    }

    /**
     * Resolves a system account by code, creating it if a society was set up
     * before the account existed.
     */
    public function account(Society $society, string $code): LedgerAccount
    {
        return LedgerAccount::query()
            ->withoutGlobalScopes()
            ->firstOrCreate(
                ['society_id' => $society->id, 'code' => $code],
                [
                    'name' => ChartOfAccounts::nameFor($code),
                    'type' => ChartOfAccounts::typeFor($code),
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
    }
}
