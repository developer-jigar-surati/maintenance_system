<?php

namespace App\Livewire\Onboarding;

use App\Enums\Permission;
use App\Models\BillingPlan;
use App\Models\Block;
use App\Models\ChargeHead;
use App\Models\Unit;
use App\Services\Billing\RateWriter;
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

    // Step 1 - identity
    public string $type = 'apartment';

    public string $areaUnit = 'sqft';

    public int $financialYearStart = 4;

    public string $city = '';

    // Step 2 - structure
    public string $blockNames = '';

    public string $unitPattern = '';

    // Step 3 - charges

    /**
     * How maintenance is worked out.
     *
     * The old step listed every seeded charge head with its basis already
     * chosen, and asked for a rate against each: "Maintenance Charges, rate
     * per sq.ft." A committee does not decide it that way. Nearly all of them
     * take one amount per home; some vary it by building or by size; a few
     * genuinely bill by area. Ask that first, then ask only for the numbers
     * that answer it.
     *
     * Not called $method: Livewire's own update payload uses that key, and
     * the property never reaches the view.
     */
    public string $rateBasis = 'flat';

    /** The one amount, when every home pays the same. */
    public ?float $flatAmount = null;

    /** Amounts keyed by block id, when buildings differ. */
    public array $blockAmounts = [];

    /** Amounts keyed by configuration, when a 2BHK and a 3BHK differ. */
    public array $sizeAmounts = [];

    /**
     * Amounts keyed "blockId|configuration", when both matter at once.
     *
     * The case this was missing: A at 12,000, B at 11,000, the GHI block at
     * 8,000, and inside each of them the 2BHK and the 3BHK differ again.
     */
    public array $gridAmounts = [];

    /** The rate per unit of area, when billed by area. */
    public ?float $areaRate = null;

    /** Paying a year at once, for less than twelve months of it. */
    public bool $offersAdvance = false;

    public ?float $advanceAmount = null;

    /** Whether the extras beyond maintenance are showing. */
    public bool $showExtras = false;

    /** Rates for those extras, keyed by charge head id. */
    public array $rates = [];

    public string $cycle = 'monthly';

    public int $dueAfterDays = 15;

    // Step 4 - collection
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
                'rateBasis' => 'required|in:flat,by_block,by_size,by_block_and_size,by_area',
                'flatAmount' => 'required_if:rateBasis,flat|nullable|numeric|min:0|max:10000000',
                'areaRate' => 'required_if:rateBasis,by_area|nullable|numeric|min:0|max:100000',
                'blockAmounts.*' => 'nullable|numeric|min:0|max:10000000',
                'sizeAmounts.*' => 'nullable|numeric|min:0|max:10000000',
                'gridAmounts.*' => 'nullable|numeric|min:0|max:10000000',
                'advanceAmount' => 'nullable|numeric|min:0|max:100000000',
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

    /**
     * Turn the committee's answer into rates.
     *
     * Everything lands on the maintenance head: one default rate, plus a
     * charge_rates row per building or per size where they differ. The
     * remaining heads are only touched if the committee opened the extras.
     */
    private function saveCharges($society): void
    {
        DB::transaction(function () use ($society) {
            $maintenance = ChargeHead::where('code', 'MAINT')->first();

            if ($maintenance !== null) {
                $this->applyMaintenance($society, $maintenance);
            }

            $active = collect()->when($maintenance && $this->maintenanceIsSet(), fn ($c) => $c->push($maintenance));

            // Water, sinking fund, parking and the rest, only if they asked
            // for them. Most societies take one amount and nothing else.
            foreach ($this->rates as $headId => $rate) {
                $head = ChargeHead::find($headId);

                if ($head === null || $head->code === 'MAINT') {
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

            $plan->forceFill([
                'cycle' => $this->cycle,
                'due_after_days' => $this->dueAfterDays,
            ] + $this->advanceTerms())->save();

            $plan->chargeHeads()->sync(
                $active->unique('id')->values()
                    ->mapWithKeys(fn ($h, $i) => [$h->id => ['sort_order' => $i]])->all()
            );
        });
    }

    private function maintenanceIsSet(): bool
    {
        return match ($this->rateBasis) {
            'flat' => (float) $this->flatAmount > 0,
            'by_area' => (float) $this->areaRate > 0,
            'by_block' => collect($this->blockAmounts)->filter(fn ($v) => (float) $v > 0)->isNotEmpty(),
            'by_size' => collect($this->sizeAmounts)->filter(fn ($v) => (float) $v > 0)->isNotEmpty(),
            'by_block_and_size' => collect($this->gridAmounts)->filter(fn ($v) => (float) $v > 0)->isNotEmpty(),
            default => false,
        };
    }

    private function applyMaintenance($society, ChargeHead $head): void
    {
        app(RateWriter::class)->write($society, $head, [
            'basis' => $this->rateBasis,
            'flat' => $this->rateBasis === 'by_area' ? $this->areaRate : $this->flatAmount,
            'blocks' => $this->blockAmounts,
            'sizes' => $this->sizeAmounts,
            'grid' => $this->gridAmounts,
        ]);
    }

    /**
     * The advance discount, stored as a percentage.
     *
     * A committee decides it as an amount ("a year is 1,20,000 instead of
     * 1,44,000") but a percentage survives the next rate revision, so the
     * interface takes the amount and the database keeps the proportion.
     *
     * @return array<string, mixed>
     */
    private function advanceTerms(): array
    {
        $periods = $this->periodsPerYear();
        $full = $this->typicalPeriodAmount() * $periods;

        if (! $this->offersAdvance || $this->advanceAmount === null || $full <= 0) {
            return ['advance_periods' => null, 'advance_discount_percent' => null];
        }

        $discount = max(0, min(90, round((1 - ((float) $this->advanceAmount / $full)) * 100, 2)));

        return ['advance_periods' => $periods, 'advance_discount_percent' => $discount];
    }

    public function periodsPerYear(): int
    {
        return match ($this->cycle) {
            'monthly' => 12,
            'bi_monthly' => 6,
            'quarterly' => 4,
            'half_yearly' => 2,
            default => 1,
        };
    }

    /** What one period costs a typical home, for showing the year's total. */
    public function typicalPeriodAmount(): float
    {
        return match ($this->rateBasis) {
            'flat' => (float) $this->flatAmount,
            'by_block' => (float) collect($this->blockAmounts)->filter()->avg(),
            'by_size' => (float) collect($this->sizeAmounts)->filter()->avg(),
            'by_block_and_size' => (float) collect($this->gridAmounts)->filter()->avg(),
            default => 0.0,
        };
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
        $society = app(SocietyContext::class)->check();
        $heads = ChargeHead::income()->active()->orderBy('sort_order')->get();

        return view('livewire.onboarding.wizard', [
            'society' => $society,
            'heads' => $heads,
            // Maintenance is asked about on its own terms; the rest are the
            // optional extras behind "anything else".
            'extraHeads' => $heads->where('code', '!=', 'MAINT')->values(),
            'unitCount' => Unit::count(),
            'blockCount' => Block::count(),
            'blocks' => Block::orderBy('sort_order')->orderBy('name')->get(),
            // Only the sizes this society actually has, so nobody is asked
            // about a 4BHK they do not own.
            'sizes' => Unit::query()
                ->whereNotNull('configuration')
                ->where('configuration', '!=', '')
                ->distinct()
                ->orderBy('configuration')
                ->pluck('configuration'),
        ])->title('Set up your society');
    }
}
