<?php

namespace Tests\Feature\Property;

use App\Enums\Role;
use App\Livewire\Property\SitePlanView;
use App\Models\Block;
use App\Models\Unit;
use App\Services\Property\SitePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SitePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_buildings_nobody_has_positioned_are_laid_out_without_overlapping(): void
    {
        $plan = app(SitePlan::class);

        foreach ([1, 2, 3, 4, 5, 9, 12] as $count) {
            $slots = $plan->autoLayout($count);

            $this->assertCount($count, $slots);

            foreach ($slots as $i => $a) {
                $this->assertLessThanOrEqual(100, $a['x'] + $a['w'], "slot {$i} runs off the right of the plot");
                $this->assertLessThanOrEqual(100, $a['y'] + $a['h'], "slot {$i} runs off the bottom of the plot");

                foreach (array_slice($slots, $i + 1) as $j => $b) {
                    $overlaps = $a['x'] < $b['x'] + $b['w'] && $b['x'] < $a['x'] + $a['w']
                        && $a['y'] < $b['y'] + $b['h'] && $b['y'] < $a['y'] + $a['h'];

                    $this->assertFalse($overlaps, "with {$count} buildings, two of them overlap");
                }
            }
        }
    }

    public function test_the_automatic_layout_leaves_the_landmark_band_clear(): void
    {
        // The gate, parking and garden are seeded along the bottom. A
        // building drawn over them makes the plan unreadable.
        foreach (app(SitePlan::class)->autoLayout(6) as $slot) {
            $this->assertLessThanOrEqual(80, $slot['y'] + $slot['h']);
        }
    }

    public function test_a_positioned_building_keeps_its_position_and_an_unpositioned_one_is_placed(): void
    {
        $society = $this->makeSociety();

        $placed = Block::create([
            'society_id' => $society->id, 'name' => 'A', 'kind' => 'wing',
            'plan_x' => 10, 'plan_y' => 12, 'plan_width' => 30, 'plan_height' => 40,
        ]);
        Block::create(['society_id' => $society->id, 'name' => 'B', 'kind' => 'wing']);

        $shapes = app(SitePlan::class)->blocks($society)->keyBy('name');

        $this->assertSame([10, 12, 30, 40], [
            $shapes['A']['x'], $shapes['A']['y'], $shapes['A']['width'], $shapes['A']['height'],
        ]);
        $this->assertTrue($shapes['A']['arranged']);
        $this->assertFalse($shapes['B']['arranged']);
        $this->assertNotNull($shapes['B']['x']);
    }

    public function test_floors_are_stacked_top_first_the_way_a_building_is_drawn(): void
    {
        $society = $this->makeSociety();
        $block = $this->makeBlock($society, 'A');

        foreach ([0, 1, 2, 1, 0] as $i => $floor) {
            $this->makeUnit($society, [
                'block_id' => $block->id,
                'unit_number' => (string) (100 * max($floor, 1) + $i),
                'floor' => $floor,
            ]);
        }

        $stack = app(SitePlan::class)->floorStack($block);

        $this->assertSame([2, 1, 0], $stack->pluck('floor')->all());
        $this->assertSame(['2nd floor', '1st floor', 'Ground'], $stack->pluck('label')->all());
        $this->assertSame([1, 2, 2], $stack->map(fn ($f) => $f['units']->count())->all());
    }

    public function test_floors_read_the_way_people_name_them(): void
    {
        $plan = app(SitePlan::class);

        $this->assertSame('Basement 1', $plan->floorLabel(-1));
        $this->assertSame('Ground', $plan->floorLabel(0));
        $this->assertSame('1st floor', $plan->floorLabel(1));
        $this->assertSame('2nd floor', $plan->floorLabel(2));
        $this->assertSame('3rd floor', $plan->floorLabel(3));
        $this->assertSame('4th floor', $plan->floorLabel(4));
        $this->assertSame('11th floor', $plan->floorLabel(11));
        $this->assertSame('21st floor', $plan->floorLabel(21));
    }

    public function test_every_view_gives_a_unit_a_tone_and_a_meaning_in_words(): void
    {
        $society = $this->makeSociety();
        $block = $this->makeBlock($society, 'A');

        $unit = $this->makeUnit($society, [
            'block_id' => $block->id, 'unit_number' => '101', 'floor' => 1,
            'occupancy_status' => 'rented',
        ]);

        $loaded = app(SitePlan::class)->floorStack($block)->first()['units']->first();
        $plan = app(SitePlan::class);

        foreach (array_keys(SitePlan::VIEWS) as $view) {
            $this->assertNotSame('', $plan->toneFor($loaded, $view));
            $this->assertNotSame('', $plan->meaningFor($loaded, $view), "the {$view} view says nothing in words");
            $this->assertNotSame([], $plan->legendFor($view));
        }

        $this->assertSame('info', $plan->toneFor($loaded, 'occupancy'));
        $this->assertSame('Rented', $plan->meaningFor($loaded, 'occupancy'));
    }

    public function test_the_dues_view_separates_paid_up_from_what_is_owed(): void
    {
        $society = $this->makeSociety();
        $plan = app(SitePlan::class);

        $paid = new Unit(['occupancy_status' => 'rented']);
        $paid->balance_due = 0;

        $small = new Unit(['occupancy_status' => 'rented']);
        $small->balance_due = 1200;

        $large = new Unit(['occupancy_status' => 'rented']);
        $large->balance_due = 24000;

        $this->assertSame('positive', $plan->toneFor($paid, 'dues'));
        $this->assertSame('caution', $plan->toneFor($small, 'dues'));
        $this->assertSame('critical', $plan->toneFor($large, 'dues'));
        $this->assertStringContainsString('Nothing outstanding', $plan->meaningFor($paid, 'dues'));
        $this->assertStringContainsString('24,000', $plan->meaningFor($large, 'dues'));
    }

    public function test_both_views_draw_the_same_buildings_from_the_same_figures(): void
    {
        $society = $this->makeSociety();
        $block = $this->makeBlock($society, 'A');
        $this->makeUnit($society, ['block_id' => $block->id, 'unit_number' => '101', 'floor' => 1]);

        $officer = $this->makeUser($society, Role::SOCIETY_ADMIN);

        foreach (['2d', '3d'] as $mode) {
            Livewire::actingAs($officer)
                ->test(SitePlanView::class)
                ->set('mode', $mode)
                ->assertOk()
                ->assertSee('A');
        }
    }

    public function test_a_flat_stays_a_labelled_control_in_the_3d_view(): void
    {
        // The whole reason for CSS transforms over a canvas: a flat in the 3D
        // view is the same button with the same label as in the flat plan, so
        // it still works by keyboard and still reads out.
        $society = $this->makeSociety();
        $block = $this->makeBlock($society, 'A');
        $this->makeUnit($society, [
            'block_id' => $block->id, 'unit_number' => '101', 'floor' => 1,
            'occupancy_status' => 'rented',
        ]);

        Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(SitePlanView::class)
            ->set('mode', '3d')
            ->set('blockId', $block->id)
            ->assertSee('A-101, 1st floor. Rented.');
    }

    public function test_an_unknown_view_mode_falls_back_to_the_flat_plan(): void
    {
        $society = $this->makeSociety();

        Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(SitePlanView::class)
            ->set('mode', 'hologram')
            ->assertSet('mode', '2d');
    }

    public function test_buildings_can_be_arranged_from_whichever_view_you_are_in(): void
    {
        // A drag is projected back through the scene's own rotation, so
        // arranging works at any angle and no longer forces the flat plan.
        $society = $this->makeSociety();
        $this->makeBlock($society, 'A');

        $officer = $this->makeUser($society, Role::SOCIETY_ADMIN);

        foreach (['2d', '3d'] as $mode) {
            Livewire::actingAs($officer)
                ->test(SitePlanView::class)
                ->set('mode', $mode)
                ->call('startArranging')
                ->assertSet('mode', $mode)
                ->assertSet('arranging', true);
        }
    }

    public function test_a_dragged_building_cannot_be_pushed_off_the_plot(): void
    {
        // The browser clamps while dragging; this is the same guarantee on
        // the way in, for a position that arrives some other way.
        $society = $this->makeSociety();
        $block = Block::create(['society_id' => $society->id, 'name' => 'A', 'kind' => 'wing']);

        Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
            ->test(SitePlanView::class)
            ->call('startArranging')
            ->set('positions', [$block->id => ['x' => 200, 'y' => -50, 'width' => 30, 'height' => 40]])
            ->call('saveArrangement');

        $saved = $block->fresh();

        $this->assertSame(70, $saved->plan_x, 'a building was left hanging off the right of the plot');
        $this->assertSame(0, $saved->plan_y);
        $this->assertLessThanOrEqual(100, $saved->plan_x + $saved->plan_width);
    }

    public function test_a_site_plan_shows_only_its_own_societys_buildings(): void
    {
        $first = $this->makeSociety();
        $this->makeBlock($first, 'A');

        $second = $this->makeSociety();
        $this->makeBlock($second, 'Z');

        $this->actingWithinSociety($first);

        $this->assertSame(['Z'], app(SitePlan::class)->blocks($second)->pluck('name')->all());
        $this->assertSame(['A'], app(SitePlan::class)->blocks($first)->pluck('name')->all());
    }
}
