<?php

namespace Tests\Feature\Billing;

use App\Enums\Role;
use App\Livewire\Billing\UnitCharges;
use App\Models\BillingPlan;
use App\Models\ChargeHead;
use App\Models\ChargeRate;
use App\Models\UnitChargeOverride;
use App\Services\Billing\ChargeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The flat that was settled individually.
 *
 * Most homes pay their building's amount. The exceptions are real: a
 * concession agreed at an AGM, a ground floor flat left out of the lift
 * charge. They used to live in the treasurer's memory, which is why they were
 * applied one year and forgotten the next.
 */
class PerHomeAmountTest extends TestCase
{
    use RefreshDatabase;

    private function maintenance(): ChargeHead
    {
        $head = ChargeHead::where('code', 'MAINT')->firstOrFail();

        $head->forceFill([
            'basis' => 'fixed_per_unit',
            'default_rate' => 12000,
            'is_active' => true,
            'is_taxable' => false,
        ])->save();

        return $head->fresh();
    }

    private function plan(array $codes = ['MAINT']): BillingPlan
    {
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

        $heads = ChargeHead::whereIn('code', $codes)->get();
        $plan->chargeHeads()->sync($heads->mapWithKeys(fn ($h, $i) => [$h->id => ['sort_order' => $i]])->all());

        return $plan->load('chargeHeads', 'society');
    }

    public function test_an_amount_set_for_one_home_beats_its_building(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();
        $block = $this->makeBlock($society, 'A');

        $settled = $this->makeUnit($society, ['unit_number' => '101', 'block_id' => $block->id]);
        $neighbour = $this->makeUnit($society, ['unit_number' => '102', 'block_id' => $block->id]);

        ChargeRate::create([
            'society_id' => $society->id,
            'charge_head_id' => $head->id,
            'scope' => 'block',
            'block_id' => $block->id,
            'rate' => 14000,
        ]);

        UnitChargeOverride::create([
            'society_id' => $society->id,
            'unit_id' => $settled->id,
            'charge_head_id' => $head->id,
            'rate' => 9000,
            'reason' => 'AGM concession',
        ]);

        $calculator = app(ChargeCalculator::class);

        $this->assertSame(9000.0, $calculator->for($settled, $head)['rate']);
        $this->assertSame(14000.0, $calculator->for($neighbour, $head)['rate'], 'the neighbour keeps the building amount');
    }

    public function test_the_card_says_where_each_amount_came_from(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();
        $block = $this->makeBlock($society, 'GHI');

        $unit = $this->makeUnit($society, ['unit_number' => '101', 'block_id' => $block->id, 'configuration' => '3BHK']);

        $calculator = app(ChargeCalculator::class);

        $this->assertSame('society', $calculator->explain($unit, $head)['source']);

        ChargeRate::create([
            'society_id' => $society->id, 'charge_head_id' => $head->id,
            'scope' => 'block', 'block_id' => $block->id, 'rate' => 8000,
        ]);

        $this->assertSame('block', $calculator->explain($unit->fresh(), $head)['source']);

        ChargeRate::create([
            'society_id' => $society->id, 'charge_head_id' => $head->id,
            'scope' => 'block_configuration', 'block_id' => $block->id,
            'configuration' => '3BHK', 'rate' => 9500,
        ]);

        $this->assertSame('block_configuration', $calculator->explain($unit->fresh(), $head)['source']);

        UnitChargeOverride::create([
            'society_id' => $society->id, 'unit_id' => $unit->id,
            'charge_head_id' => $head->id, 'rate' => 7000,
        ]);

        $resolved = $calculator->explain($unit->fresh(), $head);

        $this->assertSame('unit', $resolved['source']);
        $this->assertSame(7000.0, $resolved['rate']);
    }

    public function test_a_home_can_be_left_out_of_a_charge_altogether(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();
        $unit = $this->makeUnit($society, ['unit_number' => '001']);

        UnitChargeOverride::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'charge_head_id' => $head->id,
            'is_exempt' => true,
            'reason' => 'Ground floor, no lift access',
        ]);

        $this->assertNull(app(ChargeCalculator::class)->for($unit, $head));
        $this->assertTrue(app(ChargeCalculator::class)->explain($unit, $head)['exempt']);
    }

    public function test_an_amount_set_for_one_home_only_applies_while_it_is_in_force(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();
        $unit = $this->makeUnit($society, ['unit_number' => '101']);

        UnitChargeOverride::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'charge_head_id' => $head->id,
            'rate' => 5000,
            'effective_from' => now()->addMonth()->toDateString(),
        ]);

        $this->assertSame(12000.0, app(ChargeCalculator::class)->for($unit, $head)['rate']);
        $this->assertSame(5000.0, app(ChargeCalculator::class)->for($unit, $head, null, now()->addMonths(2))['rate']);
    }

    // --- the screen -------------------------------------------------------

    public function test_the_committee_sets_an_amount_for_one_home_from_its_page(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();
        $this->plan();
        $unit = $this->makeUnit($society, ['unit_number' => '101']);
        $admin = $this->makeUser($society, Role::SOCIETY_ADMIN);

        Livewire::actingAs($admin)
            ->test(UnitCharges::class, ['unit' => $unit])
            ->call('edit', $head->id)
            ->assertSet('amount', 12000.0)
            ->set('amount', 9000)
            ->set('reason', 'AGM concession')
            ->call('save')
            ->assertHasNoErrors();

        $override = UnitChargeOverride::where('unit_id', $unit->id)->firstOrFail();

        $this->assertSame('9000.0000', (string) $override->rate);
        $this->assertSame('AGM concession', $override->reason);
        $this->assertSame($admin->id, $override->set_by);
    }

    public function test_a_home_can_be_put_back_on_the_shared_amount(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();
        $this->plan();
        $unit = $this->makeUnit($society, ['unit_number' => '101']);

        UnitChargeOverride::create([
            'society_id' => $society->id, 'unit_id' => $unit->id,
            'charge_head_id' => $head->id, 'rate' => 9000,
        ]);

        Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(UnitCharges::class, ['unit' => $unit])
            ->call('useSharedAmount', $head->id);

        $this->assertSame(0, UnitChargeOverride::where('unit_id', $unit->id)->count());
        $this->assertSame(12000.0, app(ChargeCalculator::class)->for($unit->fresh(), $head)['rate']);
    }

    public function test_an_amount_is_required_unless_the_home_is_left_out(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();
        $this->plan();
        $unit = $this->makeUnit($society, ['unit_number' => '101']);

        Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(UnitCharges::class, ['unit' => $unit])
            ->call('edit', $head->id)
            ->set('amount', null)
            ->call('save')
            ->assertHasErrors(['amount' => 'required'])
            ->set('isExempt', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(UnitChargeOverride::where('unit_id', $unit->id)->firstOrFail()->is_exempt);
    }

    public function test_a_resident_cannot_change_what_their_home_pays(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();
        $this->plan();
        $unit = $this->makeUnit($society, ['unit_number' => '101']);

        Livewire::actingAs($this->makeUser($society, Role::OWNER))
            ->test(UnitCharges::class, ['unit' => $unit])
            ->call('edit', $head->id)
            ->assertForbidden();
    }
}
