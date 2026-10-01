<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\LateFeeRule;
use App\Models\Unit;
use App\Services\Billing\LateFeeCalculator;
use App\Support\SocietyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Interest is the part residents will argue about, so it is tested hardest:
 * the arithmetic, and the guarantee that a run cannot charge twice.
 */
class LateFeeTest extends TestCase
{
    use RefreshDatabase;

    private function overdueInvoice(float $balance = 10000, int $daysOverdue = 40): Invoice
    {
        $society = app(SocietyContext::class)->check();
        $unit = Unit::first() ?? $this->makeUnit($society);

        $invoice = Invoice::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'invoice_number' => 'T/INV/'.fake()->unique()->numberBetween(1, 99999),
            'issue_date' => now()->subDays($daysOverdue + 15),
            'due_date' => now()->subDays($daysOverdue),
            'status' => 'overdue',
        ]);

        // Totals are derived from lines, so the principal has to be a real
        // line rather than a number written onto the header.
        $invoice->lines()->save(
            (new InvoiceLine([
                'description' => 'Maintenance charges',
                'quantity' => 1,
                'rate' => $balance,
                'source' => 'charge',
            ]))->computeTotals()
        );

        return $invoice->load('lines')->recalculate();
    }

    private function rule(array $attributes = []): LateFeeRule
    {
        $rule = LateFeeRule::first();
        $rule->forceFill(array_merge([
            'method' => 'percent_per_annum',
            'rate' => 18,
            'grace_days' => 0,
            'compounding' => 'simple',
            'accrual_frequency' => 'monthly',
            'minimum_amount' => 0,
            'maximum_amount' => null,
            'applies_above_amount' => 0,
            'is_active' => true,
        ], $attributes))->save();

        return $rule->fresh();
    }

    public function test_annual_interest_is_charged_one_month_at_a_time(): void
    {
        $this->makeSociety();
        $invoice = $this->overdueInvoice(balance: 10000);

        $line = app(LateFeeCalculator::class)->accrue($invoice, $this->rule(['rate' => 18]));

        // 10,000 x 18% / 12 = 150.00 for the month
        $this->assertNotNull($line);
        $this->assertSame('150.00', (string) $line->line_total);
    }

    public function test_monthly_rate_is_charged_directly(): void
    {
        $this->makeSociety();
        $invoice = $this->overdueInvoice(balance: 20000);

        $line = app(LateFeeCalculator::class)->accrue(
            $invoice,
            $this->rule(['method' => 'percent_per_month', 'rate' => 2]),
        );

        $this->assertSame('400.00', (string) $line->line_total);
    }

    public function test_a_flat_penalty_ignores_the_balance(): void
    {
        $this->makeSociety();
        $invoice = $this->overdueInvoice(balance: 99999);

        $line = app(LateFeeCalculator::class)->accrue(
            $invoice,
            $this->rule(['method' => 'flat', 'rate' => 500]),
        );

        $this->assertSame('500.00', (string) $line->line_total);
    }

    public function test_running_the_same_period_twice_does_not_charge_twice(): void
    {
        $this->makeSociety();
        $invoice = $this->overdueInvoice();
        $rule = $this->rule();

        $calculator = app(LateFeeCalculator::class);

        $first = $calculator->accrue($invoice->fresh('lines'), $rule);
        $second = $calculator->accrue($invoice->fresh('lines'), $rule);
        $third = $calculator->accrue($invoice->fresh('lines'), $rule);

        $this->assertNotNull($first);
        $this->assertNull($second, 'A second accrual in the same period must be refused.');
        $this->assertNull($third);

        $this->assertSame(1, $invoice->fresh()->lines()->where('source', 'late_fee')->count());
    }

    public function test_interest_is_not_charged_during_the_grace_period(): void
    {
        $this->makeSociety();
        $invoice = $this->overdueInvoice(daysOverdue: 5);

        $line = app(LateFeeCalculator::class)->accrue($invoice, $this->rule(['grace_days' => 10]));

        $this->assertNull($line);
    }

    public function test_bills_below_the_threshold_are_not_penalised(): void
    {
        $this->makeSociety();
        $invoice = $this->overdueInvoice(balance: 400);

        $line = app(LateFeeCalculator::class)->accrue(
            $invoice,
            $this->rule(['applies_above_amount' => 500]),
        );

        $this->assertNull($line);
    }

    public function test_interest_is_capped_at_the_maximum(): void
    {
        $this->makeSociety();
        $invoice = $this->overdueInvoice(balance: 1000000);

        $line = app(LateFeeCalculator::class)->accrue(
            $invoice,
            $this->rule(['rate' => 24, 'maximum_amount' => 1000]),
        );

        $this->assertSame('1000.00', (string) $line->line_total);
    }

    public function test_simple_interest_does_not_compound_on_earlier_interest(): void
    {
        $this->makeSociety();
        $invoice = $this->overdueInvoice(balance: 10000);
        $rule = $this->rule(['rate' => 12, 'accrual_frequency' => 'monthly']);

        $calculator = app(LateFeeCalculator::class);

        // First month: 10,000 x 12% / 12 = 100
        $first = $calculator->accrue($invoice->fresh('lines'), $rule);
        $this->assertSame('100.00', (string) $first->line_total);

        // Next month the balance is 10,100, but simple interest still charges
        // on the 10,000 principal.
        $second = $calculator->accrue($invoice->fresh('lines'), $rule, now()->addMonth());
        $this->assertSame('100.00', (string) $second->line_total);
    }

    public function test_compound_interest_charges_on_the_grown_balance(): void
    {
        $this->makeSociety();
        $invoice = $this->overdueInvoice(balance: 10000);
        $rule = $this->rule(['rate' => 12, 'compounding' => 'compound']);

        $calculator = app(LateFeeCalculator::class);

        $calculator->accrue($invoice->fresh('lines'), $rule);
        $second = $calculator->accrue($invoice->fresh('lines'), $rule, now()->addMonth());

        // 10,100 x 12% / 12 = 101.00
        $this->assertSame('101.00', (string) $second->line_total);
    }

    public function test_the_invoice_total_grows_by_the_interest_posted(): void
    {
        $this->makeSociety();
        $invoice = $this->overdueInvoice(balance: 10000);

        app(LateFeeCalculator::class)->accrue($invoice, $this->rule(['rate' => 18]));

        $invoice->refresh();

        $this->assertSame('150.00', (string) $invoice->late_fee_total);
        $this->assertSame('10150.00', (string) $invoice->total);
        $this->assertSame('10150.00', (string) $invoice->balance);
    }

    public function test_a_paid_invoice_accrues_nothing(): void
    {
        $this->makeSociety();
        $invoice = $this->overdueInvoice(balance: 10000);
        $invoice->forceFill(['balance' => 0, 'amount_paid' => 10000, 'status' => 'paid'])->save();

        $line = app(LateFeeCalculator::class)->accrue($invoice->fresh(), $this->rule());

        $this->assertNull($line);
    }
}
