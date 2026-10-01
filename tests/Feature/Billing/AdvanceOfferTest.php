<?php

namespace Tests\Feature\Billing;

use App\Enums\Role;
use App\Livewire\Billing\BillingPlanIndex;
use App\Models\AdvanceDiscount;
use App\Models\BillingPlan;
use App\Models\ChargeHead;
use App\Models\ChargeRate;
use App\Services\Billing\AdvanceOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Twelve thousand a month, or a lakh twenty if you pay the year together."
 *
 * Every society offers this and none of them could record it, so the figure
 * lived in the treasurer's head and residents heard whichever version the
 * person they asked remembered. It is decided in money and stored as a
 * percentage, because a percentage is the only form that still means the same
 * thing once A wing pays 12,000 and the GHI block pays 8,000.
 */
class AdvanceOfferTest extends TestCase
{
    use RefreshDatabase;

    private function maintenance(float $rate = 12000): ChargeHead
    {
        $head = ChargeHead::where('code', 'MAINT')->firstOrFail();

        $head->forceFill([
            'basis' => 'fixed_per_unit',
            'default_rate' => $rate,
            'is_active' => true,
            'is_taxable' => false,
        ])->save();

        return $head->fresh();
    }

    private function plan(array $attributes = []): BillingPlan
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
        ], $attributes));

        $plan->chargeHeads()->sync([ChargeHead::where('code', 'MAINT')->firstOrFail()->id => ['sort_order' => 0]]);

        return $plan->load('chargeHeads', 'society');
    }

    public function test_a_home_is_told_what_it_saves_by_paying_the_year_together(): void
    {
        $society = $this->makeSociety();
        $this->maintenance(12000);

        $plan = $this->plan(['advance_periods' => 12, 'advance_discount_percent' => 16.67]);
        $unit = $this->makeUnit($society, ['unit_number' => '101']);

        $offer = app(AdvanceOffer::class)->for($unit, $plan);

        $this->assertSame(144000.0, $offer['full']);
        $this->assertEqualsWithDelta(120000.0, $offer['payable'], 10.0);
        $this->assertEqualsWithDelta(24000.0, $offer['saving'], 10.0);
        $this->assertSame(12, $offer['periods']);
    }

    public function test_a_building_can_be_given_a_different_deal(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance(12000);

        $a = $this->makeBlock($society, 'A');
        $ghi = $this->makeBlock($society, 'GHI');

        ChargeRate::create([
            'society_id' => $society->id, 'charge_head_id' => $head->id,
            'scope' => 'block', 'block_id' => $ghi->id, 'rate' => 8000,
        ]);

        $plan = $this->plan(['advance_periods' => 12, 'advance_discount_percent' => 10]);

        AdvanceDiscount::create([
            'society_id' => $society->id,
            'billing_plan_id' => $plan->id,
            'block_id' => $ghi->id,
            'discount_percent' => 25,
        ]);

        $plan->load('advanceDiscounts');

        $inA = $this->makeUnit($society, ['unit_number' => '101', 'block_id' => $a->id]);
        $inGhi = $this->makeUnit($society, ['unit_number' => '201', 'block_id' => $ghi->id]);

        $service = app(AdvanceOffer::class);

        // A wing pays the society's 12,000 and gets the society's ten percent.
        $this->assertSame(10.0, $service->for($inA, $plan)['percent']);
        $this->assertSame(129600.0, $service->for($inA, $plan)['payable']);

        // GHI pays 8,000 and was promised a quarter off.
        $this->assertSame(25.0, $service->for($inGhi, $plan)['percent']);
        $this->assertSame(96000.0, $service->for($inGhi, $plan)['full']);
        $this->assertSame(72000.0, $service->for($inGhi, $plan)['payable']);
    }

    public function test_a_building_told_it_gets_nothing_does_not_inherit_the_society_offer(): void
    {
        $society = $this->makeSociety();
        $this->maintenance(12000);

        $block = $this->makeBlock($society, 'B');
        $plan = $this->plan(['advance_periods' => 12, 'advance_discount_percent' => 16.67]);

        AdvanceDiscount::create([
            'society_id' => $society->id,
            'billing_plan_id' => $plan->id,
            'block_id' => $block->id,
            'discount_percent' => 0,
        ]);

        $plan->load('advanceDiscounts');

        $unit = $this->makeUnit($society, ['unit_number' => '101', 'block_id' => $block->id]);

        $this->assertNull(app(AdvanceOffer::class)->for($unit, $plan));
    }

    public function test_a_yearly_plan_offers_nothing_because_it_is_already_one_bill(): void
    {
        $society = $this->makeSociety();
        $this->maintenance(144000);

        $plan = $this->plan(['cycle' => 'yearly', 'advance_periods' => 1, 'advance_discount_percent' => 10]);
        $unit = $this->makeUnit($society, ['unit_number' => '101']);

        $this->assertSame(1, $plan->periodsPerYear());
        $this->assertNull(app(AdvanceOffer::class)->for($unit, $plan));
    }

    public function test_the_reference_year_follows_what_each_building_actually_pays(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance(12000);

        $a = $this->makeBlock($society, 'A');
        $ghi = $this->makeBlock($society, 'GHI');

        ChargeRate::create([
            'society_id' => $society->id, 'charge_head_id' => $head->id,
            'scope' => 'block', 'block_id' => $ghi->id, 'rate' => 8000,
        ]);

        $this->makeUnit($society, ['unit_number' => '101', 'block_id' => $a->id]);
        $this->makeUnit($society, ['unit_number' => '201', 'block_id' => $ghi->id]);

        $reference = app(AdvanceOffer::class)->typicalYearly($this->plan());

        $this->assertSame(144000.0, $reference['blocks'][$a->id]);
        $this->assertSame(96000.0, $reference['blocks'][$ghi->id]);
        // Two flats, one of each: the typical year sits between them.
        $this->assertSame(120000.0, $reference['society']);
    }

    // --- the screen -------------------------------------------------------

    public function test_the_committee_types_a_yearly_amount_and_a_percentage_is_stored(): void
    {
        $society = $this->makeSociety();
        $this->maintenance(12000);
        $plan = $this->plan();
        $this->makeUnit($society, ['unit_number' => '101']);

        Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(BillingPlanIndex::class)
            ->call('editAdvance', $plan->id)
            ->assertSet('offersAdvance', false)
            ->set('offersAdvance', true)
            ->set('yearAmount', 120000)
            ->call('saveAdvance')
            ->assertHasNoErrors();

        $plan->refresh();

        $this->assertSame(12, $plan->advance_periods);
        // A year is 1,44,000 at 12,000 a month; 1,20,000 is a sixth off.
        $this->assertSame('16.67', (string) $plan->advance_discount_percent);
    }

    public function test_a_building_given_its_own_yearly_amount_gets_its_own_row(): void
    {
        $society = $this->makeSociety();
        $this->maintenance(12000);
        $plan = $this->plan();

        $ghi = $this->makeBlock($society, 'GHI');
        $this->makeUnit($society, ['unit_number' => '201', 'block_id' => $ghi->id]);

        Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(BillingPlanIndex::class)
            ->call('editAdvance', $plan->id)
            ->set('offersAdvance', true)
            ->set('yearAmount', 130000)
            ->set('blockYearAmounts.'.$ghi->id, 108000)
            ->call('saveAdvance')
            ->assertHasNoErrors();

        $discount = AdvanceDiscount::where('billing_plan_id', $plan->id)->where('block_id', $ghi->id)->firstOrFail();

        // GHI's own year is 1,44,000, and 1,08,000 is a quarter off it.
        $this->assertSame('25.00', (string) $discount->discount_percent);
    }

    public function test_the_stored_percentage_is_shown_back_as_money(): void
    {
        $society = $this->makeSociety();
        $this->maintenance(12000);
        $plan = $this->plan(['advance_periods' => 12, 'advance_discount_percent' => 16.67]);
        $this->makeUnit($society, ['unit_number' => '101']);

        Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(BillingPlanIndex::class)
            ->call('editAdvance', $plan->id)
            ->assertSet('offersAdvance', true)
            // 16.67% off 1,44,000. Not exactly the 1,20,000 that was typed,
            // because a percentage rounded to two places cannot hold every
            // amount, and a percentage is what keeps working when the
            // maintenance itself changes.
            ->assertSet('yearAmount', 119995.2);
    }

    public function test_turning_the_offer_off_clears_every_building_deal(): void
    {
        $society = $this->makeSociety();
        $this->maintenance(12000);
        $plan = $this->plan(['advance_periods' => 12, 'advance_discount_percent' => 16.67]);
        $block = $this->makeBlock($society, 'A');
        $this->makeUnit($society, ['unit_number' => '101', 'block_id' => $block->id]);

        AdvanceDiscount::create([
            'society_id' => $society->id, 'billing_plan_id' => $plan->id,
            'block_id' => $block->id, 'discount_percent' => 20,
        ]);

        Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(BillingPlanIndex::class)
            ->call('editAdvance', $plan->id)
            ->set('offersAdvance', false)
            ->call('saveAdvance')
            ->assertHasNoErrors();

        $plan->refresh();

        $this->assertNull($plan->advance_periods);
        $this->assertNull($plan->advance_discount_percent);
        $this->assertSame(0, AdvanceDiscount::where('billing_plan_id', $plan->id)->count());
    }

    public function test_a_resident_cannot_change_the_offer(): void
    {
        $society = $this->makeSociety();
        $this->maintenance(12000);
        $plan = $this->plan();

        Livewire::actingAs($this->makeUser($society, Role::OWNER))
            ->test(BillingPlanIndex::class)
            ->call('editAdvance', $plan->id)
            ->assertForbidden();
    }
}
