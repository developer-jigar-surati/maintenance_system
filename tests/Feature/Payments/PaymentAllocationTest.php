<?php

namespace Tests\Feature\Payments;

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Receipt;
use App\Models\Unit;
use App\Services\Payments\PaymentRecorder;
use App\Services\Payments\ReceiptIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(Unit $unit, float $amount, int $dueInDays = 15): Invoice
    {
        $invoice = Invoice::create([
            'society_id' => $unit->society_id,
            'unit_id' => $unit->id,
            'invoice_number' => 'T/INV/'.fake()->unique()->numberBetween(1, 999999),
            'issue_date' => now(),
            'due_date' => now()->addDays($dueInDays),
            'status' => 'issued',
        ]);

        $invoice->lines()->save(
            (new InvoiceLine(['description' => 'Charges', 'quantity' => 1, 'rate' => $amount, 'source' => 'charge']))
                ->computeTotals()
        );

        return $invoice->load('lines')->recalculate();
    }

    public function test_a_payment_settles_the_oldest_bill_first(): void
    {
        $society = $this->makeSociety(['settings' => ['payments' => ['offline_requires_approval' => false]]]);
        $unit = $this->makeUnit($society);
        $admin = $this->makeUser($society, Role::TREASURER);

        $oldest = $this->invoice($unit, 1000, dueInDays: -60);
        $middle = $this->invoice($unit, 1000, dueInDays: -30);
        $newest = $this->invoice($unit, 1000, dueInDays: 15);

        app(PaymentRecorder::class)->recordOffline($unit, ['amount' => 1500, 'method' => 'cash'], $admin);

        $this->assertSame('paid', $oldest->fresh()->status);
        $this->assertSame('partially_paid', $middle->fresh()->status);
        $this->assertSame('500.00', (string) $middle->fresh()->balance);
        $this->assertSame('issued', $newest->fresh()->status);
        $this->assertSame('1000.00', (string) $newest->fresh()->balance);
    }

    public function test_an_overpayment_is_kept_as_credit_rather_than_refused(): void
    {
        $society = $this->makeSociety(['settings' => ['payments' => ['offline_requires_approval' => false]]]);
        $unit = $this->makeUnit($society);
        $admin = $this->makeUser($society, Role::TREASURER);

        $invoice = $this->invoice($unit, 1000);

        $payment = app(PaymentRecorder::class)
            ->recordOffline($unit, ['amount' => 2500, 'method' => 'upi'], $admin);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('1500.00', (string) $payment->fresh()->unallocated_amount);
        $this->assertEqualsWithDelta(1500.0, $unit->fresh()->creditBalance(), 0.001);
    }

    public function test_credit_is_applied_to_the_next_bill_raised(): void
    {
        $society = $this->makeSociety(['settings' => ['payments' => ['offline_requires_approval' => false]]]);
        $unit = $this->makeUnit($society);
        $admin = $this->makeUser($society, Role::TREASURER);

        $recorder = app(PaymentRecorder::class);

        // Pay in advance with nothing outstanding.
        $recorder->recordOffline($unit, ['amount' => 3000, 'method' => 'neft'], $admin);
        $this->assertEqualsWithDelta(3000.0, $recorder->creditBalanceFor($unit), 0.001);

        $invoice = $this->invoice($unit, 1200);
        $applied = $recorder->applyCreditTo($invoice);

        $this->assertEqualsWithDelta(1200.0, $applied, 0.001);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEqualsWithDelta(1800.0, $recorder->creditBalanceFor($unit), 0.001);
    }

    public function test_an_offline_payment_waits_for_approval_when_the_society_requires_it(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $admin = $this->makeUser($society, Role::TREASURER);

        $invoice = $this->invoice($unit, 1000);

        $payment = app(PaymentRecorder::class)
            ->recordOffline($unit, ['amount' => 1000, 'method' => 'cheque'], $admin);

        $this->assertSame('awaiting_approval', $payment->status);
        $this->assertSame('issued', $invoice->fresh()->status, 'An unapproved payment must not settle a bill.');
        $this->assertNull($payment->receipt);
    }

    public function test_approving_a_payment_settles_the_bill_and_issues_a_receipt(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $admin = $this->makeUser($society, Role::TREASURER);

        $invoice = $this->invoice($unit, 1000);
        $recorder = app(PaymentRecorder::class);

        $payment = $recorder->recordOffline($unit, ['amount' => 1000, 'method' => 'cash'], $admin);
        $recorder->approve($payment, $admin);

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNotNull($payment->fresh()->receipt);
    }

    public function test_a_rejected_payment_leaves_the_bill_untouched(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $admin = $this->makeUser($society, Role::TREASURER);

        $invoice = $this->invoice($unit, 1000);
        $recorder = app(PaymentRecorder::class);

        $payment = $recorder->recordOffline($unit, ['amount' => 1000, 'method' => 'cheque'], $admin);
        $recorder->reject($payment, $admin, 'Cheque bounced');

        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame('1000.00', (string) $invoice->fresh()->balance);
    }

    public function test_a_payment_cannot_be_approved_twice(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $admin = $this->makeUser($society, Role::TREASURER);

        $this->invoice($unit, 1000);
        $recorder = app(PaymentRecorder::class);

        $payment = $recorder->recordOffline($unit, ['amount' => 1000, 'method' => 'cash'], $admin);
        $recorder->approve($payment, $admin);

        $this->expectException(\DomainException::class);
        $recorder->approve($payment->fresh(), $admin);
    }

    public function test_one_payment_can_settle_several_bills(): void
    {
        $society = $this->makeSociety(['settings' => ['payments' => ['offline_requires_approval' => false]]]);
        $unit = $this->makeUnit($society);
        $admin = $this->makeUser($society, Role::TREASURER);

        $first = $this->invoice($unit, 500, dueInDays: -30);
        $second = $this->invoice($unit, 700, dueInDays: -15);

        $payment = app(PaymentRecorder::class)
            ->recordOffline($unit, ['amount' => 1200, 'method' => 'upi'], $admin);

        $this->assertSame(2, $payment->fresh()->allocations()->count());
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame('paid', $second->fresh()->status);
        $this->assertSame('0.00', (string) $payment->fresh()->unallocated_amount);
    }

    public function test_receipt_numbers_are_sequential_and_gap_free(): void
    {
        $society = $this->makeSociety(['settings' => ['payments' => ['offline_requires_approval' => false]]]);
        $admin = $this->makeUser($society, Role::TREASURER);
        $recorder = app(PaymentRecorder::class);

        foreach (range(1, 5) as $i) {
            $unit = $this->makeUnit($society);
            $this->invoice($unit, 100);
            $recorder->recordOffline($unit, ['amount' => 100, 'method' => 'cash'], $admin);
        }

        $numbers = Receipt::orderBy('id')->pluck('receipt_number')
            ->map(fn ($n) => (int) substr($n, strrpos($n, '/') + 1))
            ->all();

        $this->assertSame([1, 2, 3, 4, 5], $numbers);
    }

    public function test_a_receipt_is_issued_once_per_payment(): void
    {
        $society = $this->makeSociety(['settings' => ['payments' => ['offline_requires_approval' => false]]]);
        $unit = $this->makeUnit($society);
        $admin = $this->makeUser($society, Role::TREASURER);

        $this->invoice($unit, 1000);
        $payment = app(PaymentRecorder::class)
            ->recordOffline($unit, ['amount' => 1000, 'method' => 'cash'], $admin);

        // Completing again must not mint a second receipt.
        app(ReceiptIssuer::class)->issueFor($payment->fresh());
        app(ReceiptIssuer::class)->issueFor($payment->fresh());

        $this->assertSame(1, Receipt::where('payment_id', $payment->id)->count());
    }
}
