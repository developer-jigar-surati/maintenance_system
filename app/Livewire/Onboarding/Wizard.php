<?php

namespace App\Livewire\Onboarding;

use App\Enums\Permission;
use App\Models\Block;
use App\Models\BillingPlan;
use App\Models\ChargeHead;
use App\Models\Unit;
use App\Services\SocietyProvisioner;
use App\Support\SocietyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Guides a new society from empty to billing.
 *
 * Deliberately short: identity, structure, what to charge, and how money is
 * collected. Everything else has a sensible default and can wait.
 */
#[Layout('components.layouts.app')]
class Wizard extends Component
{
    public int $step = 1;

    // Step 1 — identity
    public string $type = 'apartment';

    public string $areaUnit = 'sqft';

    public int $financialYearStart = 4;

    public string $city = '';

    // Step 2 — structure
    public string $blockNames = '';

    public string $unitPattern = '';

    // Step 3 — charges
    public array $rates = [];

    public string $cycle = 'monthly';

    public int $dueAfterDays = 15;

    // Step 4 — collection
    public string $paymentMode = 'offline';

    public bool $requireApproval = true;

    public function mount(): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        $society = app(SocietyContext::class)->check();

        $this->type = $society->type;
        $this->areaUnit = $society->area_unit;
        $this->financialYearStart = (int) $society->financial_year_start_month;
        $this->city = (string) $society->city;
        $this->paymentMode = $society->payment_mode;

        foreach (ChargeHead::income()->active()->orderBy('sort_order')->get() as $head) {
            $this->rates[$head->id] = (float) $head->default_rate;
        }
    }

    public function next(): void
    {
        $this->validateStep();
        $this->persistStep();
        $this->step = min(4, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    private function validateStep(): void
    {
        match ($this->step) {
            1 => $this->validate([
                'type' => 'required|string',
                'areaUnit' => 'required|in:sqft,sqm,sqyd',
                'financialYearStart' => 'required|integer|min:1|max:12',
                'city' => 'nullable|string|max:80',
            ]),
            2 => $this->validate([
                'blockNames' => 'nullable|string|max:500',
                'unitPattern' => 'nullable|string|max:2000',
            ]),
            3 => $this->validate([
                'cycle' => 'required|in:monthly,bi_monthly,quarterly,half_yearly,yearly',
                'dueAfterDays' => 'required|integer|min:1|max:120',
            ]),
            default => null,
        };
    }

    private function persistStep(): void
    {
        $society = app(SocietyContext::class)->check();

        match ($this->step) {
            1 => $this->saveIdentity($society),
            2 => $this->saveStructure($society),
            3 => $this->saveCharges($society),
            default => null,
        };
    }

    private function saveIdentity($society): void
    {
        $society->forceFill([
            'type' => $this->type,
            'area_unit' => $this->areaUnit,
            'financial_year_start_month' => $this->financialYearStart,
            'city' => $this->city ?: null,
        ])->save();

        // The financial year depends on the start month, so re-open it here.
        app(SocietyProvisioner::class)->openFinancialYear($society->refresh());
    }

    /**
     * Creates blocks and units from two plain-text fields. Typing
     * "A, B" and "101-104, 201-204" is far quicker than a form per unit.
     */
    private function saveStructure($society): void
    {
        DB::transaction(function () use ($society) {
            $blocks = collect(explode(',', $this->blockNames))
                ->map(fn ($n) => trim($n))
                ->filter()
                ->map(fn ($name) => Block::firstOrCreate(
                    ['society_id' => $society->id, 'name' => $name],
                    ['kind' => 'wing'],
                ));

            $numbers = $this->expandUnitPattern($this->unitPattern);

            if ($numbers === []) {
                return;
            }

            // With no blocks named, units sit directly under the society.
            $targets = $blocks->isEmpty() ? collect([null]) : $blocks;

            foreach ($targets as $block) {
                foreach ($numbers as $number) {
                    Unit::firstOrCreate(
                        [
                            'society_id' => $society->id,
                            'block_id' => $block?->id,
                            'unit_number' => $number,
                        ],
                        ['type' => $this->defaultUnitType(), 'status' => 'active'],
                    );
                }
            }
        });
    }

    /** "101-104, 201, 203" becomes ['101','102','103','104','201','203']. */
    private function expandUnitPattern(string $pattern): array
    {
        $numbers = [];

        foreach (explode(',', $pattern) as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $chunk, $m)) {
                $from = (int) $m[1];
                $to = (int) $m[2];

                if ($to >= $from && ($to - $from) <= 500) {
                    foreach (range($from, $to) as $n) {
                        $numbers[] = (string) $n;
                    }
                }

                continue;
            }

            $numbers[] = $chunk;
        }

        return array_values(array_unique($numbers));
    }

    private function defaultUnitType(): string
    {
        return match ($this->type) {
            'villa', 'gated_community' => 'villa',
            'row_house' => 'row_house',
            'bungalow' => 'bungalow',
            'plotted_development' => 'plot',
            'commercial_complex', 'office_park' => 'office',
            default => 'flat',
        };
    }

    private function saveCharges($society): void
    {
        DB::transaction(function () use ($society) {
            $active = collect();

            foreach ($this->rates as $headId => $rate) {
                $head = ChargeHead::find($headId);

                if ($head === null) {
                    continue;
                }

                $head->forceFill(['default_rate' => (float) $rate])->save();

                if ((float) $rate > 0) {
                    $active->push($head);
                }
            }

            if ($active->isEmpty()) {
                return;
            }

            $plan = BillingPlan::firstOrCreate(
                ['society_id' => $society->id, 'name' => 'Standard maintenance'],
                [
                    'cycle' => $this->cycle,
                    'due_after_days' => $this->dueAfterDays,
                    'starts_on' => now()->startOfMonth()->toDateString(),
                    'next_run_on' => now()->startOfMonth()->toDateString(),
                    'auto_generate' => true,
                    'auto_issue' => true,
                    'is_active' => true,
                ],
            );

            $plan->forceFill(['cycle' => $this->cycle, 'due_after_days' => $this->dueAfterDays])->save();
            $plan->chargeHeads()->sync(
                $active->mapWithKeys(fn ($h, $i) => [$h->id => ['sort_order' => $i]])->all()
            );
        });
    }

    public function finish(): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        $this->validate(['paymentMode' => 'required|in:offline,online,both']);

        $society = app(SocietyContext::class)->check();

        $society->forceFill(['payment_mode' => $this->paymentMode])->save();
        $society->putSetting('payments.offline_requires_approval', $this->requireApproval);
        $society->save();

        app(SocietyProvisioner::class)->completeOnboarding($society);

        session()->flash('status', 'Setup complete. Your society is ready to bill.');
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding.wizard', [
            'society' => app(SocietyContext::class)->check(),
            'heads' => ChargeHead::income()->active()->orderBy('sort_order')->get(),
            'unitCount' => Unit::count(),
            'blockCount' => Block::count(),
        ])->title('Set up your society');
    }
}
