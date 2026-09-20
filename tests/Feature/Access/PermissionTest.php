<?php

namespace Tests\Feature\Access;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Invoice;
use App\Models\UnitResident;
use App\Services\SocietyProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_resident_cannot_reach_the_financial_reports(): void
    {
        $society = $this->makeSociety();
        $resident = $this->makeUser($society, Role::OWNER);

        $this->actingAs($resident)->get(route('reports.index'))->assertForbidden();
    }

    public function test_a_treasurer_can_reach_the_financial_reports(): void
    {
        $society = $this->makeSociety();
        $treasurer = $this->makeUser($society, Role::TREASURER);

        $this->actingAs($treasurer)->get(route('reports.index'))->assertOk();
    }

    public function test_a_guard_reaches_the_gate_but_not_the_books(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUser($society, Role::SECURITY_GUARD);

        $this->actingAs($guard)->get(route('gate.index'))->assertOk();
        $this->actingAs($guard)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($guard)->get(route('settings.index'))->assertForbidden();
    }

    public function test_a_super_admin_passes_every_gate(): void
    {
        $society = $this->makeSociety();
        $super = $this->makeUser($society, Role::OWNER, ['is_super_admin' => true]);

        $this->actingAs($super)->get(route('reports.index'))->assertOk();
        $this->actingAs($super)->get(route('settings.index'))->assertOk();
        $this->actingAs($super)->get(route('audit.index'))->assertOk();
    }

    public function test_roles_are_scoped_to_a_society(): void
    {
        $first = $this->makeSociety();
        $user = $this->makeUser($first, Role::TREASURER);

        // The same person is only an ordinary owner in the second society.
        $second = $this->makeSociety();
        app(SocietyProvisioner::class)->attachAdministrator($second, $user, Role::OWNER);

        $this->actingWithinSociety($first);
        $this->assertTrue($user->fresh()->hasRole(Role::TREASURER));

        $this->actingWithinSociety($second);
        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasRole(Role::OWNER));
        $this->assertFalse($fresh->hasRole(Role::TREASURER), 'A role must not leak between societies.');
    }

    public function test_a_treasurer_holds_the_money_permissions_and_not_the_rest(): void
    {
        $society = $this->makeSociety();
        $treasurer = $this->makeUser($society, Role::TREASURER);

        $this->assertTrue($treasurer->can(Permission::PAYMENT_APPROVE));
        $this->assertTrue($treasurer->can(Permission::INVOICE_GENERATE));
        $this->assertTrue($treasurer->can(Permission::ACCOUNTING_MANAGE));

        $this->assertFalse($treasurer->can(Permission::GATE_OPERATE));
        $this->assertFalse($treasurer->can(Permission::STAFF_MANAGE));
    }

    public function test_a_resident_sees_only_their_own_invoices(): void
    {
        $society = $this->makeSociety();
        $mine = $this->makeUnit($society, ['unit_number' => '101']);
        $theirs = $this->makeUnit($society, ['unit_number' => '102']);

        $resident = $this->makeUser($society, Role::OWNER);

        UnitResident::create([
            'society_id' => $society->id,
            'unit_id' => $mine->id,
            'user_id' => $resident->id,
            'relation' => 'owner',
            'is_primary' => true,
            'is_billing_contact' => true,
            'status' => 'active',
        ]);

        foreach ([$mine, $theirs] as $unit) {
            Invoice::create([
                'society_id' => $society->id,
                'unit_id' => $unit->id,
                'invoice_number' => 'T/INV/'.$unit->unit_number,
                'issue_date' => now(),
                'due_date' => now()->addDays(15),
                'total' => 1000,
                'balance' => 1000,
                'status' => 'issued',
            ]);
        }

        $response = $this->actingAs($resident)->get(route('invoices.index'));

        $response->assertOk();
        $response->assertSee('T/INV/101');
        $response->assertDontSee('T/INV/102');
    }

    public function test_a_resident_cannot_open_another_units_invoice(): void
    {
        $society = $this->makeSociety();
        $theirs = $this->makeUnit($society, ['unit_number' => '202']);
        $resident = $this->makeUser($society, Role::OWNER);

        $invoice = Invoice::create([
            'society_id' => $society->id,
            'unit_id' => $theirs->id,
            'invoice_number' => 'T/INV/202',
            'issue_date' => now(),
            'due_date' => now()->addDays(15),
            'total' => 1000,
            'balance' => 1000,
            'status' => 'issued',
        ]);

        $this->actingAs($resident)->get(route('invoices.show', $invoice))->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
