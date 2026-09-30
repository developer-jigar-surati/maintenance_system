<?php

namespace App\Livewire\Property;

use App\Enums\Permission;
use App\Models\Block;
use App\Models\SiteFeature;
use App\Models\Unit;
use App\Services\Property\SitePlan;
use App\Support\SocietyContext;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The society drawn as a plan: buildings on the site, flats inside a building.
 *
 * Answers the question a list cannot -- "which flat, and where is it" -- and
 * lets a committee see a pattern at a glance: a whole wing in arrears, a
 * floor full of vacancies.
 */
#[Layout('components.layouts.app')]
class SitePlanView extends Component
{
    /** Which block is open. Null shows the site from above. */
    #[Url(except: null, as: 'block')]
    public ?int $blockId = null;

    /** What the colours mean: occupancy, dues or complaints. */
    #[Url(except: 'occupancy')]
    public string $view = 'occupancy';

    /** The unit whose details panel is showing. */
    public ?int $selectedUnitId = null;

    /** Arrange mode, for positioning buildings on the plan. */
    public bool $arranging = false;

    public array $positions = [];

    public function updatedView(string $value): void
    {
        if (! array_key_exists($value, SitePlan::VIEWS)) {
            $this->view = 'occupancy';
        }
    }

    public function openBlock(int $blockId): void
    {
        $this->blockId = $blockId;
        $this->selectedUnitId = null;
    }

    public function backToSite(): void
    {
        $this->blockId = null;
        $this->selectedUnitId = null;
    }

    public function selectUnit(int $unitId): void
    {
        $this->selectedUnitId = $this->selectedUnitId === $unitId ? null : $unitId;
    }

    // --- arranging ---------------------------------------------------------

    public function startArranging(SitePlan $plan): void
    {
        Gate::authorize(Permission::UNIT_MANAGE);

        $society = app(SocietyContext::class)->check();

        // Seed the form from the plan as it currently reads, including the
        // automatic grid, so arranging starts from what is on screen rather
        // than from a page of blanks.
        $this->positions = $plan->blocks($society)
            ->mapWithKeys(fn (array $b) => [$b['id'] => [
                'x' => $b['x'], 'y' => $b['y'], 'width' => $b['width'], 'height' => $b['height'],
            ]])
            ->all();

        $this->arranging = true;
    }

    public function saveArrangement(): void
    {
        Gate::authorize(Permission::UNIT_MANAGE);

        foreach ($this->positions as $id => $position) {
            $block = Block::find($id);

            if ($block === null) {
                continue;
            }

            $width = (int) max(6, min(100, (int) ($position['width'] ?? 20)));
            $height = (int) max(6, min(100, (int) ($position['height'] ?? 24)));

            $block->forceFill([
                // Clamped so a building can never be dragged off the plot.
                'plan_x' => max(0, min(100 - $width, (int) ($position['x'] ?? 0))),
                'plan_y' => max(0, min(100 - $height, (int) ($position['y'] ?? 0))),
                'plan_width' => $width,
                'plan_height' => $height,
            ])->save();
        }

        $this->arranging = false;
        $this->dispatch('notify', message: 'Site plan saved.', tone: 'positive');
    }

    public function cancelArranging(): void
    {
        $this->arranging = false;
        $this->positions = [];
    }

    /** Puts every building back on the automatic grid. */
    public function resetArrangement(SitePlan $plan): void
    {
        Gate::authorize(Permission::UNIT_MANAGE);

        Block::query()->update([
            'plan_x' => null, 'plan_y' => null, 'plan_width' => null, 'plan_height' => null,
        ]);

        $this->arranging = false;
        $this->positions = [];
        $this->dispatch('notify', message: 'Buildings put back on the default grid.', tone: 'positive');
    }

    public function render(SitePlan $plan)
    {
        $society = app(SocietyContext::class)->check();
        $block = $this->blockId ? Block::find($this->blockId) : null;

        $selected = $this->selectedUnitId
            ? Unit::with(['block', 'activeResidents.user'])
                ->withCount(['complaints as open_complaints_count' => fn ($q) => $q->open()])
                ->withSum(['invoices as balance_due' => fn ($q) => $q->open()], 'balance')
                ->find($this->selectedUnitId)
            : null;

        return view('livewire.property.site-plan', [
            'society' => $society,
            'block' => $block,
            'blocks' => $plan->blocks($society),
            'features' => SiteFeature::query()->orderBy('sort_order')->get(),
            'floors' => $block ? $plan->floorStack($block) : collect(),
            'selected' => $selected,
            'plan' => $plan,
            'legend' => $plan->legendFor($this->view),
            'views' => SitePlan::VIEWS,
            'canArrange' => auth()->user()->can(Permission::UNIT_MANAGE),
        ])->title($block ? "Plan — {$block->name}" : 'Site plan');
    }
}
