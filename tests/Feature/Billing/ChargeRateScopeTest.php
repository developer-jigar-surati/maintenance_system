<?php

namespace Tests\Feature\Billing;

use App\Enums\Role;
use App\Livewire\Onboarding\Wizard;
use App\Models\BillingPlan;
use App\Models\ChargeHead;
use App\Models\ChargeRate;
use App\Models\UnitChargeOverride;
use App\Services\Billing\ChargeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Maintenance is one amount for most societies, but not for all of them.
 *
 * A wing with a lift pays more than one without, and a 3BHK pays more than a
 * 2BHK. Saying that used to mean an override row on every single flat, which
 * is why nobody did it and the numbers lived in the treasurer's head.
 */
class ChargeRateScopeTest extends TestCase
{
    use RefreshDatabase;

    private function maintenance(): ChargeHead
    {
        $head = ChargeHead::where('code', 'MAINT')->firstOrFail();

        $head->forceFill(['basis' => 'fixed_per_unit', 'default_rate' => 1000, 'is_active' => true])->save();

        return $head->fresh();
    }

    public function test_one_amount_covers_every_home(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();
        $unit = $this->makeUnit($society, ['unit_number' => '101']);

        $line = app(ChargeCalculator::class)->for($unit, $head);

        $this->assertSame(1000.0, $line['rate']);
    }

    public function test_a_building_can_charge_its_own_amount(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();

        $withLift = $this->makeBlock($society, 'A');
        $without = $this->makeBlock($society, 'B');

        $first = $this->makeUnit($society, ['unit_number' => '101', 'block_id' => $withLift->id]);
        $second = $this->makeUnit($society, ['unit_number' => '201', 'block_id' => $without->id]);

        ChargeRate::create([
            'society_id' => $society->id,
            'charge_head_id' => $head->id,
            'scope' => 'block',
            'block_id' => $withLift->id,
            'rate' => 1400,
        ]);

        $calculator = app(ChargeCalculator::class);

        $this->assertSame(1400.0, $calculator->for($first, $head)['rate']);
        $this->assertSame(1000.0, $calculator->for($second, $head)['rate'], 'the other wing should be unaffected');
    }

    public function test_a_size_of_home_can_charge_its_own_amount(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();

        $small = $this->makeUnit($society, ['unit_number' => '101', 'configuration' => '2BHK']);
        $large = $this->makeUnit($society, ['unit_number' => '102', 'configuration' => '3BHK']);

        foreach (['2BHK' => 900, '3BHK' => 1500] as $configuration => $rate) {
            ChargeRate::create([
                'society_id' => $society->id,
                'charge_head_id' => $head->id,
                'scope' => 'configuration',
                'configuration' => $configuration,
                'rate' => $rate,
            ]);
        }

        $calculator = app(ChargeCalculator::class);

        $this->assertSame(900.0, $calculator->for($small, $head)['rate']);
        $this->assertSame(1500.0, $calculator->for($large, $head)['rate']);
    }

    public function test_the_more_specific_statement_wins(): void
    {
        // "3BHK pays more" is a more deliberate statement about this flat
        // than "B wing pays more", and a rate set on the flat itself beats
        // both.
        $society = $this->makeSociety();
        $head = $this->maintenance();
        $block = $this->makeBlock($society, 'B');

        $unit = $this->makeUnit($society, [
            'unit_number' => '301', 'block_id' => $block->id, 'configuration' => '3BHK',
        ]);

        ChargeRate::create([
            'society_id' => $society->id, 'charge_head_id' => $head->id,
            'scope' => 'block', 'block_id' => $block->id, 'rate' => 1400,
        ]);

        $this->assertSame(1400.0, app(ChargeCalculator::class)->for($unit, $head)['rate']);

        ChargeRate::create([
            'society_id' => $society->id, 'charge_head_id' => $head->id,
            'scope' => 'configuration', 'configuration' => '3BHK', 'rate' => 1800,
        ]);

        $this->assertSame(1800.0, app(ChargeCalculator::class)->for($unit->fresh(), $head)['rate']);

        UnitChargeOverride::create([
            'society_id' => $society->id, 'unit_id' => $unit->id,
            'charge_head_id' => $head->id, 'rate' => 500, 'reason' => 'Committee decision',
        ]);

        $this->assertSame(500.0, app(ChargeCalculator::class)->for($unit->fresh(), $head)['rate']);
    }

    public function test_a_rate_for_one_society_never_reaches_another(): void
    {
        $first = $this->makeSociety();
        $firstHead = $this->maintenance();
        $block = $this->makeBlock($first, 'A');

        ChargeRate::create([
            'society_id' => $first->id, 'charge_head_id' => $firstHead->id,
            'scope' => 'block', 'block_id' => $block->id, 'rate' => 9999,
        ]);

        $second = $this->makeSociety();
        $secondHead = $this->maintenance();
        $unit = $this->makeUnit($second, ['unit_number' => '101']);

        $this->assertSame(1000.0, app(ChargeCalculator::class)->for($unit, $secondHead)['rate']);
    }

    // --- the question the committee is asked ------------------------------

    public function test_one_amount_and_a_yearly_discount_is_recorded_the_way_it_is_described(): void
    {
        // Twelve thousand a month, or a hundred and twenty for the year.
        $society = $this->makeSociety();
        $this->maintenance();

        Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(Wizard::class)
            ->set('step', 3)
            ->set('rateBasis', 'flat')
            ->set('flatAmount', 12000)
            ->set('cycle', 'monthly')
            ->set('offersAdvance', true)
            ->set('advanceAmount', 120000)
            ->call('next');

        $head = ChargeHead::where('code', 'MAINT')->firstOrFail();

        $this->assertSame('fixed_per_unit', $head->basis);
        $this->assertSame('12000.0000', (string) $head->default_rate);

        $plan = BillingPlan::where('name', 'Standard maintenance')->firstOrFail();

        $this->assertSame(12, $plan->advance_periods);
        // A year is 1,44,000 at the monthly rate; 1,20,000 is a sixth off.
        $this->assertSame('16.67', (string) $plan->advance_discount_percent);
    }

    public function test_answering_by_building_writes_a_rate_for_each_one(): void
    {
        $society = $this->makeSociety();
        $this->maintenance();

        $a = $this->makeBlock($society, 'A');
        $b = $this->makeBlock($society, 'B');

        Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(Wizard::class)
            ->set('step', 3)
            ->set('rateBasis', 'by_block')
            ->set('blockAmounts', [$a->id => 1400, $b->id => 1000])
            ->call('next');

        $head = ChargeHead::where('code', 'MAINT')->firstOrFail();
        $rates = ChargeRate::where('charge_head_id', $head->id)->get();

        $this->assertCount(2, $rates);
        $this->assertSame('1400.0000', (string) $rates->firstWhere('block_id', $a->id)->rate);
        $this->assertSame('1000.0000', (string) $rates->firstWhere('block_id', $b->id)->rate);
    }

    public function test_changing_the_answer_clears_the_previous_answers_rates(): void
    {
        // Switching from per building to one amount must not leave the old
        // building rates quietly billing people.
        $society = $this->makeSociety();
        $this->maintenance();
        $block = $this->makeBlock($society, 'A');

        $component = Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(Wizard::class)
            ->set('step', 3)
            ->set('rateBasis', 'by_block')
            ->set('blockAmounts', [$block->id => 1400])
            ->call('next');

        $this->assertSame(1, ChargeRate::count());

        $component
            ->set('step', 3)
            ->set('rateBasis', 'flat')
            ->set('flatAmount', 12000)
            ->call('next');

        $this->assertSame(0, ChargeRate::count());
    }
}
