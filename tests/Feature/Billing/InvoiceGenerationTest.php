<?php

namespace Tests\Feature\Billing;

use App\Models\BillingPlan;
use App\Models\ChargeHead;
use App\Models\Invoice;
use App\Models\UnitChargeOverride;
use App\Services\Billing\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $codes, array $overrides = []): BillingPlan
    {
        $plan = BillingPlan::create(array_merge([
            'name' => 'Monthly',
            'cycle' => 'monthly',
            'due_after_days' => 15,
            'starts_on' => now()->startOfMonth(),
            'next_run_on' => now()->startOfMonth(),
            'auto_generate' => true,
            'auto_issue' => true,
            'is_active' => true,
        ], $overrides));

        $heads = ChargeHead::whereIn('code', $codes)->get();
        $plan->chargeHeads()->sync($heads->mapWithKeys(fn ($h, $i) => [$h->id => ['sort_order' => $i]])->all());

        return $plan->load('chargeHeads', 'society');
    }

    public function test_per_square_foot_charges_multiply_rate_by_area(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society, ['carpet_area' => 1250]);

        $this->setChargeRate('MAINT', 2.50, 'per_sqft');

        $result = app(InvoiceGenerator::class)->run($this->plan(['MAINT']));

        $invoice = $result['invoices']->first();

        // 1250 sq.ft. x 2.50 = 3125.00
        $this->assertSame('3125.00', (string) $invoice->total);
        $this->assertSame('3125.00', (string) $invoice->balance);
        $this->assertSame('issued', $invoice->status);
    }

    public function test_fixed_charges_are_the_same_for_every_unit(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society, ['carpet_area' => 500]);
        $this->makeUnit($society, ['carpet_area' => 3000]);

        $this->setChargeRate('WATER', 300, 'fixed_per_unit');

        $result = app(InvoiceGenerator::class)->run($this->plan(['WATER']));

        $this->assertSame(2, $result['created']);
        $this->assertEqualsWithDelta(600.0, $result['total'], 0.001);
        $this->assertSame(['300.00', '300.00'], $result['invoices']->pluck('total')->map('strval')->all());
    }

    public function test_several_heads_are_summed_onto_one_invoice(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society, ['carpet_area' => 1000]);

        $this->setChargeRate('MAINT', 2.00, 'per_sqft');   // 2000
        $this->setChargeRate('SINK', 0.50, 'per_sqft');    //  500
        $this->setChargeRate('WATER', 250, 'fixed_per_unit'); // 250

        $result = app(InvoiceGenerator::class)->run($this->plan(['MAINT', 'SINK', 'WATER']));

        $invoice = $result['invoices']->first();

        $this->assertSame(3, $invoice->lines()->count());
        $this->assertSame('2750.00', (string) $invoice->total);
    }

    public function test_a_second_run_for_the_same_period_bills_nobody_twice(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society);
        $this->setChargeRate('MAINT', 1000);

        $plan = $this->plan(['MAINT']);

        $first = app(InvoiceGenerator::class)->run($plan);
        $second = app(InvoiceGenerator::class)->run($plan->fresh('chargeHeads', 'society'));

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, Invoice::count());
    }

    public function test_units_marked_not_billable_are_skipped(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society, ['unit_number' => '101']);
        $this->makeUnit($society, ['unit_number' => '102', 'is_billable' => false]);

        $this->setChargeRate('MAINT', 500);

        $result = app(InvoiceGenerator::class)->run($this->plan(['MAINT']));

        $this->assertSame(1, $result['created']);
        $this->assertSame('101', $result['invoices']->first()->unit->unit_number);
    }

    public function test_a_unit_level_exemption_removes_the_charge(): void
    {
        $society = $this->makeSociety();
        $exempt = $this->makeUnit($society, ['unit_number' => '101']);
        $this->makeUnit($society, ['unit_number' => '102']);

        $head = $this->setChargeRate('MAINT', 1000);

        UnitChargeOverride::create([
            'society_id' => $society->id,
            'unit_id' => $exempt->id,
            'charge_head_id' => $head->id,
            'is_exempt' => true,
            'reason' => 'Ground floor, no lift access',
        ]);

        $result = app(InvoiceGenerator::class)->run($this->plan(['MAINT']));

        // The exempt unit has no chargeable lines at all, so no bill is raised.
        $this->assertSame(1, $result['created']);
        $this->assertSame('102', $result['invoices']->first()->unit->unit_number);
    }

    public function test_a_unit_level_rate_override_wins_over_the_default(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);

        $head = $this->setChargeRate('MAINT', 1000);

        UnitChargeOverride::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'charge_head_id' => $head->id,
            'rate' => 250,
            'reason' => 'Negotiated rate',
        ]);

        $result = app(InvoiceGenerator::class)->run($this->plan(['MAINT']));

        $this->assertSame('250.00', (string) $result['invoices']->first()->total);
    }

    public function test_tax_is_added_when_the_head_is_taxable(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society);

        $head = $this->setChargeRate('MAINT', 1000);
        $head->forceFill(['is_taxable' => true, 'tax_rate' => 18])->save();

        $result = app(InvoiceGenerator::class)->run($this->plan(['MAINT']));
        $invoice = $result['invoices']->first();

        $this->assertSame('1000.00', (string) $invoice->subtotal);
        $this->assertSame('180.00', (string) $invoice->tax_total);
        $this->assertSame('1180.00', (string) $invoice->total);
    }

    public function test_arrears_from_earlier_bills_are_carried_onto_the_next(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society);
        $this->setChargeRate('MAINT', 1000);

        $plan = $this->plan(['MAINT']);

        app(InvoiceGenerator::class)->run($plan, now()->subMonth());
        $second = app(InvoiceGenerator::class)->run($plan->fresh('chargeHeads', 'society'), now());

        // The first bill is still open, so it shows as arrears on the second.
        $this->assertEqualsWithDelta(1000.0, (float) $second['invoices']->first()->arrears_amount, 0.001);
    }

    public function test_a_draft_plan_produces_draft_invoices(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society);
        $this->setChargeRate('MAINT', 1000);

        $result = app(InvoiceGenerator::class)->run($this->plan(['MAINT'], ['auto_issue' => false]));

        $this->assertSame('draft', $result['invoices']->first()->status);
    }
}
