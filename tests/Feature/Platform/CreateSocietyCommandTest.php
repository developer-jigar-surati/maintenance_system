<?php

namespace Tests\Feature\Platform;

use App\Enums\Role;
use App\Models\ChargeHead;
use App\Models\LedgerAccount;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The command that creates the first society on a fresh installation, before
 * there is any console to sign in to.
 */
class CreateSocietyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_provisions_a_society(): void
    {
        $this->artisan('society:create', [
            '--name' => 'Sunrise Enclave',
            '--type' => 'gated_community',
            '--city' => 'Pune',
            '--admin-name' => 'Priya Kulkarni',
            '--admin-email' => 'priya@sunrise.test',
            '--admin-password' => 'password123',
            '--payment-mode' => 'both',
        ])->assertSuccessful();

        $society = Society::where('name', 'Sunrise Enclave')->first();

        $this->assertNotNull($society);
        $this->assertSame('gated_community', $society->type);
        $this->assertSame('Pune', $society->city);
        $this->assertSame('both', $society->payment_mode);

        $this->assertGreaterThan(0, LedgerAccount::withoutGlobalScopes()->where('society_id', $society->id)->count());
        $this->assertGreaterThan(0, ChargeHead::withoutGlobalScopes()->where('society_id', $society->id)->count());
        $this->assertNotNull($society->currentFinancialYear());
    }

    public function test_the_administrator_can_run_the_society(): void
    {
        $this->artisan('society:create', [
            '--name' => 'Harbour View',
            '--admin-name' => 'Sam Dsouza',
            '--admin-email' => 'sam@harbour.test',
            '--admin-password' => 'password123',
        ])->assertSuccessful();

        $society = Society::where('name', 'Harbour View')->firstOrFail();
        $admin = User::where('email', 'sam@harbour.test')->firstOrFail();

        $this->assertTrue($admin->belongsToSociety($society));

        setPermissionsTeamId($society->id);
        $this->assertTrue($admin->fresh()->hasRole(Role::SOCIETY_ADMIN));
    }

    public function test_it_can_also_mint_a_platform_operator(): void
    {
        $this->artisan('society:create', [
            '--name' => 'First Society',
            '--admin-name' => 'Owner One',
            '--admin-email' => 'first@example.test',
            '--admin-password' => 'password123',
            '--super-admin' => true,
        ])->assertSuccessful();

        $this->assertTrue(User::where('email', 'first@example.test')->firstOrFail()->isSuperAdmin());
    }

    public function test_the_promotion_command_grants_and_revokes(): void
    {
        $this->artisan('user:super-admin', [
            'email' => 'operator@example.test',
            '--name' => 'Operator',
            '--password' => 'password123',
        ])->assertSuccessful();

        $user = User::where('email', 'operator@example.test')->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());

        $this->artisan('user:super-admin', ['email' => 'operator@example.test', '--revoke' => true])
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->isSuperAdmin());
    }

    public function test_each_society_gets_its_own_code(): void
    {
        foreach (['Green Valley', 'Green Valley Two'] as $name) {
            $this->artisan('society:create', [
                '--name' => $name,
                '--admin-name' => 'Admin',
                '--admin-email' => Str::slug($name).'@example.test',
                '--admin-password' => 'password123',
            ])->assertSuccessful();
        }

        $codes = Society::pluck('code');

        $this->assertSame($codes->count(), $codes->unique()->count(), 'Society codes must be unique.');
    }
}
