<?php

namespace App\Services\Reporting;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Society;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Derives the financial statements from the journal, plus the collection
 * figures a committee looks at week to week.
 *
 * Everything here reads the ledger rather than re-totalling invoices, so the
 * reports agree with the books by construction.
 */
class FinancialReports
{
    /** Headline figures for the admin dashboard. */
    public function dashboard(Society $society): array
    {
        $invoices = Invoice::query()->where('society_id', $society->id);

        $outstanding = (float) (clone $invoices)->open()->sum('balance');
        $overdue = (float) (clone $invoices)->overdue()->sum('balance');

        $collectedThisMonth = (float) Payment::query()
            ->where('society_id', $society->id)
            ->completed()
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $billedThisMonth = (float) (clone $invoices)
            ->issued()
            ->whereBetween('issue_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        $spentThisMonth = (float) Expense::query()
            ->where('society_id', $society->id)
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->whereBetween('bill_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        $units = Unit::query()->where('society_id', $society->id)->billable();

        return [
            'outstanding' => round($outstanding, 2),
            'overdue' => round($overdue, 2),
            'collected_this_month' => round($collectedThisMonth, 2),
            'billed_this_month' => round($billedThisMonth, 2),
            'spent_this_month' => round($spentThisMonth, 2),
            'collection_rate' => $billedThisMonth > 0
                ? round(min(100, $collectedThisMonth / $billedThisMonth * 100), 1)
                : null,
            'total_units' => (clone $units)->count(),
            'defaulter_count' => (clone $invoices)->overdue()->distinct('unit_id')->count('unit_id'),
            'cash_and_bank' => $this->cashAndBank($society),
            'pending_approvals' => [
                'payments' => Payment::query()->where('society_id', $society->id)->pendingApproval()->count(),
                'expenses' => Expense::query()->where('society_id', $society->id)->awaitingApproval()->count(),
            ],
        ];
    }

    /** Liquid funds: every cash and bank account's current balance. */
    public function cashAndBank(Society $society): float
    {
        return round((float) LedgerAccount::query()
            ->where('society_id', $society->id)
            ->whereIn('code', ['1010', '1020', '1030'])
            ->get()
            ->sum(fn (LedgerAccount $a) => $a->balanceAsOf()), 2);
    }

    /**
     * Units with money outstanding, worst first. This is the list a committee
     * actually chases, so it carries the contact details too.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function defaulters(Society $society, int $minimumDaysOverdue = 0): Collection
    {
        return Unit::query()
            ->where('society_id', $society->id)
            ->billable()
            ->with(['block', 'activeResidents.user'])
            ->withSum(['invoices as outstanding' => fn ($q) => $q->open()], 'balance')
            ->withMin(['invoices as oldest_due_date' => fn ($q) => $q->open()], 'due_date')
            // Filtered with whereHas rather than HAVING: HAVING needs a GROUP BY
            // on SQLite, and this expresses the same thing portably.
            ->whereHas('invoices', fn ($q) => $q->open()->where('balance', '>', 0))
            ->orderByDesc('outstanding')
            ->get()
            ->map(function (Unit $unit) {
                $oldest = $unit->oldest_due_date ? Carbon::parse($unit->oldest_due_date) : null;

                return [
                    'unit' => $unit,
                    'outstanding' => round((float) $unit->outstanding, 2),
                    'days_overdue' => $oldest && $oldest->isPast() ? (int) $oldest->diffInDays(now()) : 0,
                    'contact' => $unit->billingContact()?->user,
                ];
            })
            ->filter(fn ($row) => $row['days_overdue'] >= $minimumDaysOverdue)
            ->values();
    }

    /**
     * Trial balance as at a date. Debits and credits must agree; when they do
     * not, the ledger has been written to outside LedgerPoster.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, debit: float, credit: float, balanced: bool}
     */
    public function trialBalance(Society $society, ?Carbon $asOf = null): array
    {
        $asOf ??= now();

        $movements = JournalLine::query()
            ->where('journal_lines.society_id', $society->id)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.is_posted', true)
            ->whereDate('journal_entries.entry_date', '<=', $asOf->toDateString())
            ->selectRaw('journal_lines.ledger_account_id, SUM(journal_lines.debit) as debit, SUM(journal_lines.credit) as credit')
            ->groupBy('journal_lines.ledger_account_id')
            ->get()
            ->keyBy('ledger_account_id');

        $rows = LedgerAccount::query()
            ->where('society_id', $society->id)
            ->orderBy('code')
            ->get()
            ->map(function (LedgerAccount $account) use ($movements) {
                $row = $movements->get($account->id);
                $debit = (float) ($row->debit ?? 0);
                $credit = (float) ($row->credit ?? 0);

                $opening = (float) $account->opening_balance;
                if ($opening > 0) {
                    $account->opening_balance_side === 'debit' ? $debit += $opening : $credit += $opening;
                }

                $net = round($debit - $credit, 2);

                return [
                    'account' => $account,
                    'debit' => $net > 0 ? $net : 0.0,
                    'credit' => $net < 0 ? abs($net) : 0.0,
                ];
            })
            ->filter(fn ($r) => $r['debit'] > 0 || $r['credit'] > 0)
            ->values();

        $debit = round((float) $rows->sum('debit'), 2);
        $credit = round((float) $rows->sum('credit'), 2);

        return [
            'rows' => $rows,
            'debit' => $debit,
            'credit' => $credit,
            'balanced' => abs($debit - $credit) < 0.01,
        ];
    }

    /**
     * Income and expenditure for a period -- the statement a society presents
     * at its AGM.
     */
    public function incomeAndExpenditure(Society $society, Carbon $from, Carbon $to): array
    {
        $totals = $this->accountTotals($society, $from, $to);

        $income = $this->linesFor($society, $totals, 'income', fn ($d, $c) => $c - $d);
        $expense = $this->linesFor($society, $totals, 'expense', fn ($d, $c) => $d - $c);

        $incomeTotal = round((float) $income->sum('amount'), 2);
        $expenseTotal = round((float) $expense->sum('amount'), 2);

        return [
            'from' => $from,
            'to' => $to,
            'income' => $income,
            'expense' => $expense,
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'surplus' => round($incomeTotal - $expenseTotal, 2),
        ];
    }

    /** Balance sheet as at a date. */
    public function balanceSheet(Society $society, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $totals = $this->accountTotals($society, null, $asOf);

        $assets = $this->linesFor($society, $totals, 'asset', fn ($d, $c) => $d - $c);
        $liabilities = $this->linesFor($society, $totals, 'liability', fn ($d, $c) => $c - $d);
        $funds = $this->linesFor($society, $totals, 'equity', fn ($d, $c) => $c - $d);

        $income = round((float) $this->linesFor($society, $totals, 'income', fn ($d, $c) => $c - $d)->sum('amount'), 2);
        $expense = round((float) $this->linesFor($society, $totals, 'expense', fn ($d, $c) => $d - $c)->sum('amount'), 2);
        $surplus = round($income - $expense, 2);

        $assetTotal = round((float) $assets->sum('amount'), 2);
        $liabilityTotal = round((float) $liabilities->sum('amount'), 2);
        $fundTotal = round((float) $funds->sum('amount'), 2);

        return [
            'as_of' => $asOf,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'funds' => $funds,
            'assets_total' => $assetTotal,
            'liabilities_total' => $liabilityTotal,
            'funds_total' => $fundTotal,
            'surplus' => $surplus,
            // Assets should equal what is owed plus what is held, including
            // the period's surplus.
            'balanced' => abs($assetTotal - ($liabilityTotal + $fundTotal + $surplus)) < 0.01,
        ];
    }

    /** Twelve months of billed versus collected, for the dashboard chart. */
    public function collectionTrend(Society $society, int $months = 12): Collection
    {
        return collect(range($months - 1, 0))->map(function (int $ago) use ($society) {
            $start = now()->copy()->subMonths($ago)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            return [
                'month' => $start->format('M Y'),
                'billed' => round((float) Invoice::query()
                    ->where('society_id', $society->id)
                    ->issued()
                    ->whereBetween('issue_date', [$start, $end])
                    ->sum('total'), 2),
                'collected' => round((float) Payment::query()
                    ->where('society_id', $society->id)
                    ->completed()
                    ->whereBetween('paid_at', [$start, $end])
                    ->sum('amount'), 2),
            ];
        });
    }

    /**
     * Debit and credit movement per account over a window.
     *
     * @return Collection<int, object>
     */
    private function accountTotals(Society $society, ?Carbon $from, Carbon $to): Collection
    {
        return JournalLine::query()
            ->where('journal_lines.society_id', $society->id)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.is_posted', true)
            ->when($from, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $from->toDateString()))
            ->whereDate('journal_entries.entry_date', '<=', $to->toDateString())
            ->selectRaw('journal_lines.ledger_account_id, SUM(journal_lines.debit) as debit, SUM(journal_lines.credit) as credit')
            ->groupBy('journal_lines.ledger_account_id')
            ->get()
            ->keyBy('ledger_account_id');
    }

    /**
     * Turns raw movement into signed statement lines for one account type.
     *
     * @param  callable(float, float): float  $sign
     * @return Collection<int, array<string, mixed>>
     */
    private function linesFor(Society $society, Collection $totals, string $type, callable $sign): Collection
    {
        return LedgerAccount::query()
            ->where('society_id', $society->id)
            ->where('type', $type)
            ->orderBy('code')
            ->get()
            ->map(function (LedgerAccount $account) use ($totals, $sign) {
                $row = $totals->get($account->id);
                $amount = round($sign((float) ($row->debit ?? 0), (float) ($row->credit ?? 0)), 2);

                $opening = (float) $account->opening_balance;
                if ($opening > 0) {
                    $amount += $account->opening_balance_side === $account->normalSide() ? $opening : -$opening;
                }

                return ['account' => $account, 'amount' => round($amount, 2)];
            })
            ->filter(fn ($r) => abs($r['amount']) > 0.004)
            ->values();
    }
}
