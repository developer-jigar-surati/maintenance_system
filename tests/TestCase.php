<?php

namespace Tests;

use App\Enums\Role as RoleName;
use App\Models\Block;
use App\Models\ChargeHead;
use App\Models\Society;
use App\Models\Unit;
use App\Models\User;
use App\Services\SocietyProvisioner;
use App\Support\SocietyContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates a fully provisioned society and makes it the active one, which
     * is what most tests need before they can touch anything scoped.
     */
    protected function makeSociety(array $attributes = []): Society
    {
        $society = app(SocietyProvisioner::class)->create(array_merge([
            'name' => 'Test Society '.fake()->unique()->numberBetween(1, 99999),
            'type' => 'apartment',
            'area_unit' => 'sqft',
            'financial_year_start_month' => 4,
            'payment_mode' => 'offline',
            'status' => 'active',
        ], $attributes));

        $this->actingWithinSociety($society);

        return $society;
    }

    /** Points both the model scope and the permission team at a society. */
    protected function actingWithinSociety(Society $society): void
    {
        app(SocietyContext::class)->set($society);
        setPermissionsTeamId($society->id);
    }

    protected function makeUser(Society $society, string $role = RoleName::OWNER, array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ], $attributes));

        app(SocietyProvisioner::class)->attachAdministrator($society, $user, $role);

        return $user->fresh();
    }

    protected function makeUnit(Society $society, array $attributes = []): Unit
    {
        return Unit::create(array_merge([
            'society_id' => $society->id,
            'unit_number' => (string) fake()->unique()->numberBetween(100, 9999),
            'type' => 'flat',
            'carpet_area' => 1000,
            'occupancy_status' => 'owner_occupied',
            'is_billable' => true,
        ], $attributes));
    }

    protected function makeBlock(Society $society, string $name = 'A'): Block
    {
        return Block::create(['society_id' => $society->id, 'name' => $name, 'kind' => 'wing']);
    }

    /** Sets a rate on one of the heads the provisioner seeds. */
    protected function setChargeRate(string $code, float $rate, string $basis = 'fixed_per_unit'): ChargeHead
    {
        $head = ChargeHead::where('code', $code)->firstOrFail();
        $head->forceFill(['default_rate' => $rate, 'basis' => $basis, 'is_active' => true])->save();

        return $head->fresh();
    }

    protected function tearDown(): void
    {
        app(SocietyContext::class)->forget();

        parent::tearDown();
    }
}
