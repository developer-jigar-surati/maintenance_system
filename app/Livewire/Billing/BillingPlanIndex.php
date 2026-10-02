<?php

namespace App\Livewire\Billing;

use App\Enums\Permission;
use App\Models\AdvanceDiscount;
use App\Models\BillingPlan;
use App\Models\Block;
use App\Services\Billing\AdvanceOffer;
use App\Services\Billing\InvoiceGenerator;
use App\Support\Money;
use App\Support\SocietyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Billing plans and the bill run itself.
 *
 * Running a plan by hand is deliberately available alongside the scheduler:
 * committees want to see the bills before the first of the month.
 */
#[Layout('components.layouts.app')]
class BillingPlanIndex extends Component
{
    /**
     * The plan whose prepayment offer is open for editing.
     *
     * Every society offers something for paying the year together and none of
     * them could record it, so residents heard whatever figure the person they
     * asked happened to remember. It is asked in money, because that is how a
     * meeting decides it, and stored as a percentage, because that is the only
     * form that survives the maintenance changing or differing by size.
     */
    public ?int $editingPlanId = null;

    public bool $offersAdvance = false;

    public ?float $yearAmount = null;

    /** Keyed by block id, for the buildings promised something different. */
    public array $blockYearAmounts = [];

    public function runNow(int $planId, InvoiceGenerator $generator): void
    {
        Gate::authorize(Permission::INVOICE_GENERATE);

        $plan = BillingPlan::with('chargeHeads', 'society')->findOrFail($planId);

        if ($plan->chargeHeads->isEmpty()) {
            $this->dispatch('notify',
                message: 'Add at least one charge head to this plan before running it.',
                tone: 'critical');

            return;
        }

        $result = $generator->run($plan, now(), auth()->id());

        $this->dispatch('notify',
            message: $result['created'] > 0
                ? "{$result['created']} invoices raised, totalling ".Money::format($result['total']).'.'
                : 'No new invoices - every unit is already billed for this period.',
            tone: $result['created'] > 0 ? 'positive' : 'info');
    }

    public function toggle(int $planId): void
    {
        Gate::authorize(Permission::BILLING_MANAGE);

        $plan = BillingPlan::findOrFail($planId);
        $plan->forceFill(['is_active' => ! $plan->is_active])->save();

        $this->dispatch('notify',
            message: $plan->is_active ? 'Plan activated.' : 'Plan paused.',
            tone: 'positive');
    }

    public function editAdvance(int $planId): void
    {
        Gate::authorize(Permission::BILLING_MANAGE);

        $plan = BillingPlan::with('advanceDiscounts')->findOrFail($planId);

        $this->editingPlanId = $plan->id;
        $this->reset(['yearAmount', 'blockYearAmounts']);
        $this->resetValidation();

        $reference = app(AdvanceOffer::class)->typicalYearly($plan);
        $this->offersAdvance = $plan->offersAdvance();

        // Shown as money, which means converting the stored percentage back
        // against the same reference it was worked out from.
        if ($plan->advance_discount_percent !== null) {
            $this->yearAmount = AdvanceOffer::amountAfter(
                $reference['society'], (float) $plan->advance_discount_percent
            );
        }

        foreach ($plan->advanceDiscounts as $discount) {
            $this->blockYearAmounts[$discount->block_id] = AdvanceOffer::amountAfter(
                $reference['blocks'][$discount->block_id] ?? $reference['society'],
                (float) $discount->discount_percent
            );
        }

        $this->dispatch('open-modal', 'advance-offer');
    }

    public function saveAdvance(): void
    {
        Gate::authorize(Permission::BILLING_MANAGE);

        $this->validate([
            'yearAmount' => ($this->offersAdvance ? 'required' : 'nullable').'|numeric|min:0|max:100000000',
            'blockYearAmounts.*' => 'nullable|numeric|min:0|max:100000000',
        ], [
            'yearAmount.required' => 'Enter what a year costs when it is paid in one go.',
        ]);

        $plan = BillingPlan::with('advanceDiscounts')->findOrFail($this->editingPlanId);
        $society = app(SocietyContext::class)->check();
        $reference = app(AdvanceOffer::class)->typicalYearly($plan);

        DB::transaction(function () use ($plan, $society, $reference) {
            AdvanceDiscount::query()->where('billing_plan_id', $plan->id)->delete();

            if (! $this->offersAdvance) {
                $plan->forceFill(['advance_periods' => null, 'advance_discount_percent' => null])->save();

                return;
            }

            $plan->forceFill([
                'advance_periods' => $plan->periodsPerYear(),
                'advance_discount_percent' => AdvanceOffer::percentOff(
                    $reference['society'], (float) $this->yearAmount
                ),
            ])->save();

            foreach ($this->blockYearAmounts as $blockId => $amount) {
                if ($amount === null || $amount === '') {
                    continue;
                }

                AdvanceDiscount::create([
                    'society_id' => $society->id,
                    'billing_plan_id' => $plan->id,
                    'block_id' => (int) $blockId,
                    'discount_percent' => AdvanceOffer::percentOff(
                        $reference['blocks'][(int) $blockId] ?? $reference['society'], (float) $amount
                    ),
                ]);
            }
        });

        $this->editingPlanId = null;
        $this->dispatch('close-modal', 'advance-offer');
        $this->dispatch('notify',
            message: $this->offersAdvance
                ? 'Prepayment offer saved for '.$plan->name.'.'
                : 'Prepayment offer removed from '.$plan->name.'.',
            detail: $this->offersAdvance
                ? 'Each home now sees what it saves by paying the year together.'
                : null,
            tone: 'positive');
    }

    public function render()
    {
        $editing = $this->editingPlanId
            ? BillingPlan::with(['chargeHeads', 'advanceDiscounts'])->find($this->editingPlanId)
            : null;

        return view('livewire.billing.billing-plan-index', [
            'editing' => $editing,
            'blocks' => $editing ? Block::orderBy('sort_order')->orderBy('name')->get() : collect(),
            'reference' => $editing ? app(AdvanceOffer::class)->typicalYearly($editing) : ['society' => 0.0, 'blocks' => []],
            'plans' => BillingPlan::with(['chargeHeads', 'lateFeeRule', 'advanceDiscounts.block'])
                ->withCount('invoices')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ])->title('Billing plans');
    }
}
