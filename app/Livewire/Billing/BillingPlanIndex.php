<?php

namespace App\Livewire\Billing;

use App\Enums\Permission;
use App\Models\BillingPlan;
use App\Services\Billing\InvoiceGenerator;
use App\Support\Money;
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
                : 'No new invoices — every unit is already billed for this period.',
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

    public function render()
    {
        return view('livewire.billing.billing-plan-index', [
            'plans' => BillingPlan::with(['chargeHeads', 'lateFeeRule'])
                ->withCount('invoices')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ])->title('Billing plans');
    }
}
