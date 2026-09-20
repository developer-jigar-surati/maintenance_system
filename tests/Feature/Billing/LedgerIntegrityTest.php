<?php

namespace Tests\Feature\Billing;

use App\Enums\Role;
use App\Models\BillingPlan;
use App\Models\ChargeHead;
use App\Models\JournalEntry;
use App\Services\Accounting\LedgerPoster;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Payments\PaymentRecorder;
use App\Services\Reporting\FinancialReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The books must agree with themselves. Every posting is balanced, and the
 * statements are derived from those postings rather than recomputed, so a
 * disagreement here means the ledger has been written to incorrectly.
 */
class LedgerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function billAndCollect(int $units = 4, float $rate = 2000): array
    {
        $society = $this->makeSociety(['settings' => ['payments' => ['offline_requires_approval' => false]]]);
        $admin = $this->makeUser($society, Role::TREASURER);

        foreach (range(1, $units) as $i) {
            $this->makeUnit($society, ['unit_number' => (string) (100 + $i)]);
        }

        $this->setChargeRate('MAINT', $rate);

        $plan = BillingPlan::create([
            'name' => 'Monthly',
            'cycle' => 'monthly',
            'due_after_days' => 15,
            'starts_on' => now()->startOfMonth(),
            'next_run_on' => now()->startOfMonth(),
            'auto_generate' => true,
            'auto_issue' => true,
            'is_active' => true,
        ]);
        $plan->chargeHeads()->sync([ChargeHead::where('code', 'MAINT')->value('id') => ['sort_order' => 0]]);

        $result = app(InvoiceGenerator::class)->run($plan->load('chargeHeads', 'society'));

        return [$society, $admin, $result];
    }

    public function test_every_journal_entry_balances(): void
    {
        [$society, $admin, $result] = $this->billAndCollect();

        $recorder = app(PaymentRecorder::class);
        foreach ($result['invoices']->take(2) as $invoice) {
            $recorder->recordOffline($invoice->unit, ['amount' => (float) $invoice->total, 'method' => 'cash'], $admin);
        }

        $entries = JournalEntry::all();

        $this->assertGreaterThan(0, $entries->count());

        foreach ($entries as $entry) {
            $this->assertTrue(
                $entry->isBalanced(),
                "Entry {$entry->entry_number} is unbalanced: {$entry->total_debit} vs {$entry->total_credit}",
            );
        }
    }

    public function test_the_trial_balance_agrees(): void
    {
        [$society, $admin, $result] = $this->billAndCollect();

        app(PaymentRecorder::class)->recordOffline(
            $result['invoices']->first()->unit,
            ['amount' => 2000, 'method' => 'upi'],
            $admin,
        );

        $trialBalance = app(FinancialReports::class)->trialBalance($society);

        $this->assertTrue($trialBalance['balanced'], 'Debits and credits must agree.');
        $this->assertEqualsWithDelta($trialBalance['debit'], $trialBalance['credit'], 0.01);
    }

    public function test_the_balance_sheet_ties_out(): void
    {
        [$society, $admin, $result] = $this->billAndCollect();

        app(PaymentRecorder::class)->recordOffline(
            $result['invoices']->first()->unit,
            ['amount' => 2000, 'method' => 'cash'],
            $admin,
        );

        $sheet = app(FinancialReports::class)->balanceSheet($society);

        $this->assertTrue($sheet['balanced'], 'Assets must equal liabilities plus funds plus surplus.');
    }

    public function test_billing_creates_a_receivable_and_recognises_income(): void
    {
        [$society, $admin, $result] = $this->billAndCollect(units: 2, rate: 1500);

        $poster = app(LedgerPoster::class);

        // Two units at 1,500 each.
        $this->assertEqualsWithDelta(
            3000.0,
            $poster->account($society, LedgerPoster::MEMBERS_RECEIVABLE)->balanceAsOf(),
            0.01,
        );
        $this->assertEqualsWithDelta(
            3000.0,
            $poster->account($society, LedgerPoster::MAINTENANCE_INCOME)->balanceAsOf(),
            0.01,
        );
    }

    public function test_collecting_reduces_the_receivable(): void
    {
        [$society, $admin, $result] = $this->billAndCollect(units: 2, rate: 1500);

        app(PaymentRecorder::class)->recordOffline(
            $result['invoices']->first()->unit,
            ['amount' => 1500, 'method' => 'cash'],
            $admin,
        );

        $poster = app(LedgerPoster::class);

        $this->assertEqualsWithDelta(1500.0, $poster->account($society, LedgerPoster::MEMBERS_RECEIVABLE)->balanceAsOf(), 0.01);
        $this->assertEqualsWithDelta(1500.0, $poster->account($society, LedgerPoster::CASH_IN_HAND)->balanceAsOf(), 0.01);
    }

    public function test_an_advance_is_held_as_a_liability_not_income(): void
    {
        [$society, $admin, $result] = $this->billAndCollect(units: 1, rate: 1000);

        app(PaymentRecorder::class)->recordOffline(
            $result['invoices']->first()->unit,
            ['amount' => 2500, 'method' => 'upi'],
            $admin,
        );

        $poster = app(LedgerPoster::class);

        // 1,000 clears the bill; the remaining 1,500 is money the society owes back.
        $this->assertEqualsWithDelta(
            1500.0,
            $poster->account($society, LedgerPoster::ADVANCE_FROM_MEMBERS)->balanceAsOf(),
            0.01,
        );
    }

    public function test_an_unbalanced_entry_is_refused(): void
    {
        $society = $this->makeSociety();
        $poster = app(LedgerPoster::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unbalanced');

        $poster->post($society, 'manual', now(), 'Deliberately wrong', [
            ['account' => $poster->account($society, LedgerPoster::CASH_IN_HAND), 'debit' => 100, 'credit' => 0],
            ['account' => $poster->account($society, LedgerPoster::BANK), 'debit' => 0, 'credit' => 90],
        ]);
    }

    public function test_a_reversal_cancels_the_original(): void
    {
        $society = $this->makeSociety();
        $poster = app(LedgerPoster::class);

        $entry = $poster->post($society, 'manual', now(), 'Original', [
            ['account' => $poster->account($society, LedgerPoster::CASH_IN_HAND), 'debit' => 500, 'credit' => 0],
            ['account' => $poster->account($society, LedgerPoster::BANK), 'debit' => 0, 'credit' => 500],
        ]);

        $poster->reverse($entry->load('lines'));

        // Net movement on both accounts is now zero.
        $this->assertEqualsWithDelta(0.0, $poster->account($society, LedgerPoster::CASH_IN_HAND)->balanceAsOf(), 0.01);
        $this->assertEqualsWithDelta(0.0, $poster->account($society, LedgerPoster::BANK)->balanceAsOf(), 0.01);
        $this->assertNotNull($entry->fresh()->reversed_by_entry_id);
    }
}
