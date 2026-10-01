<?php

namespace App\Livewire\Reporting;

use App\Services\Reporting\FinancialReports;
use App\Support\SocietyContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The financial statements, derived from the journal rather than re-totalled
 * from invoices, so they agree with the books by construction.
 */
#[Layout('components.layouts.app')]
class ReportIndex extends Component
{
    #[Url]
    public string $report = 'defaulters';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $society = app(SocietyContext::class)->check();
        $year = $society->currentFinancialYear();

        $this->from = $this->from ?: ($year?->starts_on->toDateString() ?? now()->startOfYear()->toDateString());
        $this->to = $this->to ?: now()->toDateString();
    }

    /** Streams the current report as CSV, so it opens in any spreadsheet. */
    public function export()
    {
        $society = app(SocietyContext::class)->check();
        $reports = app(FinancialReports::class);

        [$filename, $rows] = match ($this->report) {
            'trial_balance' => ['trial-balance', $this->trialBalanceRows($reports, $society)],
            'income_expenditure' => ['income-and-expenditure', $this->incomeRows($reports, $society)],
            'balance_sheet' => ['balance-sheet', $this->balanceSheetRows($reports, $society)],
            default => ['defaulters', $this->defaulterRows($reports, $society)],
        };

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, "{$society->code}-{$filename}-".now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function defaulterRows(FinancialReports $reports, $society): array
    {
        $rows = [['Unit', 'Contact', 'Phone', 'Outstanding', 'Days overdue']];

        foreach ($reports->defaulters($society) as $row) {
            $rows[] = [
                $row['unit']->label,
                $row['contact']?->name ?? '',
                $row['contact']?->phone ?? '',
                number_format($row['outstanding'], 2, '.', ''),
                $row['days_overdue'],
            ];
        }

        return $rows;
    }

    private function trialBalanceRows(FinancialReports $reports, $society): array
    {
        $data = $reports->trialBalance($society, Carbon::parse($this->to));
        $rows = [['Code', 'Account', 'Debit', 'Credit']];

        foreach ($data['rows'] as $row) {
            $rows[] = [
                $row['account']->code,
                $row['account']->name,
                number_format($row['debit'], 2, '.', ''),
                number_format($row['credit'], 2, '.', ''),
            ];
        }

        $rows[] = ['', 'Total', number_format($data['debit'], 2, '.', ''), number_format($data['credit'], 2, '.', '')];

        return $rows;
    }

    private function incomeRows(FinancialReports $reports, $society): array
    {
        $data = $reports->incomeAndExpenditure($society, Carbon::parse($this->from), Carbon::parse($this->to));
        $rows = [['Section', 'Code', 'Account', 'Amount']];

        foreach ($data['income'] as $row) {
            $rows[] = ['Income', $row['account']->code, $row['account']->name, number_format($row['amount'], 2, '.', '')];
        }
        foreach ($data['expense'] as $row) {
            $rows[] = ['Expenditure', $row['account']->code, $row['account']->name, number_format($row['amount'], 2, '.', '')];
        }

        $rows[] = ['', '', 'Surplus / (deficit)', number_format($data['surplus'], 2, '.', '')];

        return $rows;
    }

    private function balanceSheetRows(FinancialReports $reports, $society): array
    {
        $data = $reports->balanceSheet($society, Carbon::parse($this->to));
        $rows = [['Section', 'Code', 'Account', 'Amount']];

        foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'funds' => 'Funds'] as $key => $label) {
            foreach ($data[$key] as $row) {
                $rows[] = [$label, $row['account']->code, $row['account']->name, number_format($row['amount'], 2, '.', '')];
            }
        }

        return $rows;
    }

    public function render()
    {
        $society = app(SocietyContext::class)->check();
        $reports = app(FinancialReports::class);
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        return view('livewire.reporting.report-index', [
            'society' => $society,
            'data' => match ($this->report) {
                'trial_balance' => $reports->trialBalance($society, $to),
                'income_expenditure' => $reports->incomeAndExpenditure($society, $from, $to),
                'balance_sheet' => $reports->balanceSheet($society, $to),
                default => ['defaulters' => $reports->defaulters($society)],
            },
        ])->title('Reports');
    }
}
