<?php

namespace Tests\Feature\Platform;

use App\Enums\Role;
use App\Livewire\Platform\SocietyIndex;
use App\Models\ChargeHead;
use App\Models\LedgerAccount;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The platform console: the only screen that reads across societies, which is
 * exactly why it needs its own tests.
 */
class SocietyConsoleTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        $society = $this->makeSociety();

        return $this->makeUser($society, Role::OWNER, ['is_super_admin' => true]);
    }

    public function test_a_platform_operator_can_open_the_console(): void
    {
        $this->actingAs($this->operator())
            ->get(route('platform.societies'))
            ->assertOk()
            ->assertSee('All societies');
    }

    public function test_a_society_administrator_cannot(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUser($society, Role::SOCIETY_ADMIN);

        $this->actingAs($admin)->get(route('platform.societies'))->assertForbidden();
    }

    public function test_a_resident_cannot(): void
    {
        $society = $this->makeSociety();
        $resident = $this->makeUser($society, Role::OWNER);

        $this->actingAs($resident)->get(route('platform.societies'))->assertForbidden();
    }

    public function test_the_console_lists_every_society(): void
    {
        $operator = $this->operator();
        $this->makeSociety(['name' => 'Alpha Heights']);
        $this->makeSociety(['name' => 'Beta Gardens']);

        Livewire::actingAs($operator)
            ->test(SocietyIndex::class)
            ->assertSee('Alpha Heights')
            ->assertSee('Beta Gardens');
    }

    /**
     * Units carry the society global scope. Counted naively, the subquery is
     * narrowed to whichever society the operator is acting in and every other
     * row reports zero.
     */
    public function test_unit_counts_are_per_row_not_per_active_society(): void
    {
        $operator = $this->operator();

        $first = $this->makeSociety(['name' => 'Counted First']);
        foreach (range(1, 3) as $n) {
            $this->makeUnit($first, ['unit_number' => "A{$n}"]);
        }

        $second = $this->makeSociety(['name' => 'Counted Second']);
        foreach (range(1, 7) as $n) {
            $this->makeUnit($second, ['unit_number' => "B{$n}"]);
        }

        // Act inside the first, then read the list; the second must still
        // report its own seven units.
        $this->actingWithinSociety($first);

        $rows = Livewire::actingAs($operator)
            ->test(SocietyIndex::class)
            ->viewData('societies')
            ->keyBy('name');

        $this->assertSame(3, $rows['Counted First']->units_count);
        $this->assertSame(7, $rows['Counted Second']->units_count);
    }

    public function test_creating_a_society_provisions_it_completely(): void
    {
        $operator = $this->operator();

        Livewire::actingAs($operator)
            ->test(SocietyIndex::class)
            ->set('name', 'Lakeview Towers')
            ->set('newType', 'apartment')
            ->set('city', 'Ahmedabad')
            ->set('areaUnit', 'sqft')
            ->set('financialYearStart', 4)
            ->set('paymentMode', 'offline')
            ->set('adminName', 'Ravi Shah')
            ->set('adminEmail', 'ravi@lakeview.test')
            ->set('adminPassword', 'password123')
            ->call('create')
            ->assertHasNoErrors();

        $society = Society::where('name', 'Lakeview Towers')->first();

        $this->assertNotNull($society);
        $this->assertSame('onboarding', $society->status);
        $this->assertNotNull($society->code);

        // Provisioned with its books, heads and financial year ready.
        $this->assertGreaterThan(0, LedgerAccount::withoutGlobalScopes()->where('society_id', $society->id)->count());
        $this->assertGreaterThan(0, ChargeHead::withoutGlobalScopes()->where('society_id', $society->id)->count());
        $this->assertNotNull($society->currentFinancialYear());

        // And with an administrator who can actually run it.
        $admin = User::where('email', 'ravi@lakeview.test')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->belongsToSociety($society));

        setPermissionsTeamId($society->id);
        $this->assertTrue($admin->fresh()->hasRole(Role::SOCIETY_ADMIN));
    }

    public function test_an_existing_account_is_reused_not_duplicated(): void
    {
        $operator = $this->operator();
        $existing = $this->makeUser($this->makeSociety(), Role::OWNER, ['email' => 'shared@example.test']);

        Livewire::actingAs($operator)
            ->test(SocietyIndex::class)
            ->set('name', 'Second Society')
            ->set('adminName', 'Someone Else')
            ->set('adminEmail', 'shared@example.test')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame(1, User::where('email', 'shared@example.test')->count());

        $society = Society::where('name', 'Second Society')->first();
        $this->assertTrue($existing->fresh()->belongsToSociety($society));
    }

    public function test_creation_requires_a_name_and_an_administrator(): void
    {
        Livewire::actingAs($this->operator())
            ->test(SocietyIndex::class)
            ->set('name', '')
            ->set('adminName', '')
            ->set('adminEmail', 'not-an-email')
            ->call('create')
            ->assertHasErrors(['name', 'adminName', 'adminEmail']);
    }

    public function test_the_console_can_be_filtered_by_type(): void
    {
        $operator = $this->operator();
        $this->makeSociety(['name' => 'Tower Block', 'type' => 'apartment']);
        $this->makeSociety(['name' => 'Villa Estate', 'type' => 'villa']);

        Livewire::actingAs($operator)
            ->test(SocietyIndex::class)
            ->set('type', 'villa')
            ->assertSee('Villa Estate')
            ->assertDontSee('Tower Block');
    }
}
