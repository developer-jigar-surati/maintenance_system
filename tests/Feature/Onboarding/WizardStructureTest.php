<?php

namespace Tests\Feature\Onboarding;

use App\Enums\Role;
use App\Livewire\Onboarding\Wizard;
use App\Models\AdvanceDiscount;
use App\Models\BillingPlan;
use App\Models\Block;
use App\Models\ChargeHead;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Setting up a society whose buildings are not all alike.
 *
 * One pattern repeated into every building is right for a small complex and
 * wrong for most real ones: A to K wings almost never hold the same number of
 * floors, and copying 101-104 into all eleven creates homes that do not exist
 * while missing the ones that do.
 */
class WizardStructureTest extends TestCase
{
    use RefreshDatabase;

    private function wizard($society)
    {
        return Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))->test(Wizard::class);
    }

    public function test_one_pattern_fills_every_building_when_they_match(): void
    {
        $society = $this->makeSociety();

        $this->wizard($society)
            ->set('step', 2)
            ->set('blockNames', 'A, B')
            ->set('unitPattern', '101-104')
            ->call('next')
            ->assertHasNoErrors();

        $this->assertSame(2, Block::count());
        $this->assertSame(8, Unit::count());
        $this->assertSame(4, Unit::where('block_id', Block::where('name', 'A')->first()->id)->count());
    }

    public function test_each_building_can_hold_a_different_set_of_homes(): void
    {
        $society = $this->makeSociety();

        $this->wizard($society)
            ->set('step', 2)
            ->set('blockNames', 'A, B, C')
            ->set('sameUnitsEveryBlock', false)
            ->set('blockUnitPatterns', [
                'A' => '101-104, 201-204, 301-304',
                'B' => '101-106, 201-206',
                'C' => '1-8',
            ])
            ->call('next')
            ->assertHasNoErrors();

        $counts = Block::withCount('units')->get()->pluck('units_count', 'name')->all();

        $this->assertSame(['A' => 12, 'B' => 12, 'C' => 8], $counts);
        $this->assertSame(32, Unit::count());
    }

    public function test_a_building_left_empty_simply_gets_no_homes(): void
    {
        $society = $this->makeSociety();

        $this->wizard($society)
            ->set('step', 2)
            ->set('blockNames', 'A, B')
            ->set('sameUnitsEveryBlock', false)
            ->set('blockUnitPatterns', ['A' => '101-102'])
            ->call('next')
            ->assertHasNoErrors();

        $this->assertSame(2, Block::count(), 'the building is still created, ready for its homes');
        $this->assertSame(2, Unit::count());
    }

    public function test_turning_off_the_shared_pattern_starts_each_building_from_it(): void
    {
        $society = $this->makeSociety();

        $this->wizard($society)
            ->set('step', 2)
            ->set('blockNames', 'A, B')
            ->set('unitPattern', '101-104')
            ->set('sameUnitsEveryBlock', false)
            ->assertSet('blockUnitPatterns.A', '101-104')
            ->assertSet('blockUnitPatterns.B', '101-104');
    }

    public function test_naming_a_building_that_was_removed_brings_it_back(): void
    {
        $society = $this->makeSociety();

        $block = $this->makeBlock($society, 'C');
        $unit = $this->makeUnit($society, ['unit_number' => '101', 'block_id' => $block->id]);
        $unit->delete();
        $block->delete();

        // Blocks are soft deleted but the unique index is not, so this used to
        // fail outright with a duplicate key rather than reusing the row.
        $this->wizard($society)
            ->set('step', 2)
            ->set('blockNames', 'C')
            ->set('unitPattern', '101, 102')
            ->call('next')
            ->assertHasNoErrors();

        $this->assertSame(1, Block::count());
        $this->assertSame($block->id, Block::first()->id, 'the same wing, not a second one');
        $this->assertSame(2, Unit::count());
        $this->assertSame($unit->id, Unit::where('unit_number', '101')->first()->id);
    }

    public function test_running_the_step_again_only_adds_what_is_missing(): void
    {
        $society = $this->makeSociety();

        foreach ([1, 2] as $pass) {
            $this->wizard($society)
                ->set('step', 2)
                ->set('blockNames', 'A')
                ->set('unitPattern', '101-104')
                ->call('next')
                ->assertHasNoErrors();
        }

        $this->assertSame(1, Block::count());
        $this->assertSame(4, Unit::count());
    }

    // --- the yearly deal, per building ------------------------------------

    public function test_a_building_can_be_promised_its_own_yearly_deal(): void
    {
        $society = $this->makeSociety();
        ChargeHead::where('code', 'MAINT')->firstOrFail()
            ->forceFill(['basis' => 'fixed_per_unit', 'is_active' => true])->save();

        $a = $this->makeBlock($society, 'A');
        $c = $this->makeBlock($society, 'C');

        $this->wizard($society)
            ->set('step', 3)
            ->set('rateBasis', 'by_block')
            ->set('blockAmounts', [$a->id => 12000, $c->id => 8000])
            ->set('cycle', 'monthly')
            ->set('offersAdvance', true)
            ->set('advanceAmount', 110000)
            ->set('blockAdvanceAmounts', [$c->id => 80000])
            ->call('next')
            ->assertHasNoErrors();

        $plan = BillingPlan::where('name', 'Standard maintenance')->firstOrFail();

        // The society figure is worked out against a typical year: 12,000 and
        // 8,000 average to 10,000 a month, so 1,20,000, and 1,10,000 is
        // 8.33 percent off that.
        $this->assertSame('8.33', (string) $plan->advance_discount_percent);

        // C wing pays 8,000, so its year is 96,000 and 80,000 is a sixth off.
        $discounts = AdvanceDiscount::where('billing_plan_id', $plan->id)->get();

        $this->assertCount(1, $discounts, 'only the building that was promised something different');
        $this->assertSame($c->id, $discounts->first()->block_id);
        $this->assertSame('16.67', (string) $discounts->first()->discount_percent);
    }

    public function test_turning_the_offer_off_leaves_no_building_deals_behind(): void
    {
        $society = $this->makeSociety();
        ChargeHead::where('code', 'MAINT')->firstOrFail()
            ->forceFill(['basis' => 'fixed_per_unit', 'is_active' => true])->save();

        $block = $this->makeBlock($society, 'A');

        $run = fn (bool $offers) => $this->wizard($society)
            ->set('step', 3)
            ->set('rateBasis', 'by_block')
            ->set('blockAmounts', [$block->id => 12000])
            ->set('offersAdvance', $offers)
            ->set('advanceAmount', $offers ? 130000 : null)
            ->set('blockAdvanceAmounts', $offers ? [$block->id => 120000] : [])
            ->call('next')
            ->assertHasNoErrors();

        $run(true);
        $this->assertSame(1, AdvanceDiscount::count());

        $run(false);
        $this->assertSame(0, AdvanceDiscount::count());
    }

    public function test_a_yearly_amount_for_a_building_with_no_rate_is_ignored(): void
    {
        $society = $this->makeSociety();
        ChargeHead::where('code', 'MAINT')->firstOrFail()
            ->forceFill(['basis' => 'fixed_per_unit', 'is_active' => true])->save();

        $named = $this->makeBlock($society, 'A');
        $unpriced = $this->makeBlock($society, 'B');

        $this->wizard($society)
            ->set('step', 3)
            ->set('rateBasis', 'by_block')
            ->set('blockAmounts', [$named->id => 12000])
            ->set('offersAdvance', true)
            ->set('advanceAmount', 130000)
            ->set('blockAdvanceAmounts', [$unpriced->id => 90000])
            ->call('next')
            ->assertHasNoErrors();

        // Nothing to take a percentage of, so there is no honest row to write.
        $this->assertSame(0, AdvanceDiscount::count());
    }
}
