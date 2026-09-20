<?php

namespace Tests\Feature\Billing;

use App\Models\BillingPlan;
use App\Models\ChargeHead;
use App\Models\Invoice;
use App\Models\LateFeeRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The automation the user is actually buying: bills go out and interest
 * accrues without anyone remembering to press a button.
 */
class ScheduledBillingTest extends TestCase
{
    use RefreshDatabase;

    private function planFor($society, array $overrides = []): BillingPlan
    {
        $this->setChargeRate('MAINT', 1500);

        $plan = BillingPlan::create(array_merge([
            'society_id' => $society->id,
            'name' => 'Monthly',
            'cycle' => 'monthly',
            'due_after_days' => 15,
            'starts_on' => now()->startOfMonth(),
            'next_run_on' => now()->toDateString(),
            'auto_generate' => true,
            'auto_issue' => true,
            'is_active' => true,
        ], $overrides));

        $plan->chargeHeads()->sync([ChargeHead::where('code', 'MAINT')->value('id') => ['sort_order' => 0]]);

        return $plan;
    }

    public function test_the_billing_command_raises_bills_for_due_plans(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society, ['unit_number' => '101']);
        $this->makeUnit($society, ['unit_number' => '102']);
        $this->planFor($society);

        $this->artisan('billing:run')->assertSuccessful();

        $this->assertSame(2, Invoice::where('society_id', $society->id)->count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society);
        $this->planFor($society);

        $this->artisan('billing:run', ['--dry' => true])->assertSuccessful();

        $this->assertSame(0, Invoice::where('society_id', $society->id)->count());
    }

    public function test_a_plan_not_yet_due_is_left_alone(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society);
        $this->planFor($society, ['next_run_on' => now()->addMonth()->toDateString()]);

        $this->artisan('billing:run')->assertSuccessful();

        $this->assertSame(0, Invoice::where('society_id', $society->id)->count());
    }

    public function test_an_inactive_plan_is_skipped(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society);
        $this->planFor($society, ['is_active' => false]);

        $this->artisan('billing:run')->assertSuccessful();

        $this->assertSame(0, Invoice::where('society_id', $society->id)->count());
    }

    public function test_running_the_command_twice_does_not_double_bill(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society);
        $this->planFor($society);

        $this->artisan('billing:run')->assertSuccessful();
        $this->artisan('billing:run')->assertSuccessful();

        $this->assertSame(1, Invoice::where('society_id', $society->id)->count());
    }

    public function test_the_command_bills_every_society_it_finds(): void
    {
        $first = $this->makeSociety();
        $this->makeUnit($first);
        $this->planFor($first);

        $second = $this->makeSociety();
        $this->makeUnit($second);
        $this->planFor($second);

        $this->artisan('billing:run')->assertSuccessful();

        $this->assertSame(1, Invoice::where('society_id', $first->id)->count());
        $this->assertSame(1, Invoice::where('society_id', $second->id)->count());
    }

    public function test_the_run_can_be_limited_to_one_society(): void
    {
        $first = $this->makeSociety();
        $this->makeUnit($first);
        $this->planFor($first);

        $second = $this->makeSociety();
        $this->makeUnit($second);
        $this->planFor($second);

        $this->artisan('billing:run', ['--society' => $first->slug])->assertSuccessful();

        $this->assertSame(1, Invoice::where('society_id', $first->id)->count());
        $this->assertSame(0, Invoice::where('society_id', $second->id)->count());
    }

    public function test_the_accrual_command_is_safe_to_run_repeatedly(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society);
        $this->planFor($society, ['next_run_on' => now()->subMonths(2)->toDateString()]);

        $this->artisan('billing:run')->assertSuccessful();

        // Push the bill well past its due date.
        Invoice::where('society_id', $society->id)
            ->update(['due_date' => now()->subDays(45), 'status' => 'overdue']);

        LateFeeRule::where('society_id', $society->id)
            ->update(['is_active' => true, 'rate' => 18, 'grace_days' => 0]);

        $this->artisan('billing:accrue-late-fees')->assertSuccessful();
        $this->artisan('billing:accrue-late-fees')->assertSuccessful();
        $this->artisan('billing:accrue-late-fees')->assertSuccessful();

        $invoice = Invoice::where('society_id', $society->id)->first();

        $this->assertSame(
            1,
            $invoice->lines()->where('source', 'late_fee')->count(),
            'Interest must be posted once per period however often the command runs.',
        );
    }
}
