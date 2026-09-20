<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\Role as RoleName;
use App\Models\ChargeHead;
use App\Models\ComplaintCategory;
use App\Models\FinancialYear;
use App\Models\LateFeeRule;
use App\Models\Society;
use App\Models\User;
use App\Services\Accounting\ChartOfAccounts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Stands up a new society so it is usable the moment it is created.
 *
 * A committee should not have to invent a chart of accounts or a helpdesk
 * taxonomy before they can raise their first bill, so sensible defaults are
 * installed up front and can be edited afterwards.
 */
class SocietyProvisioner
{
    public function __construct(private ChartOfAccounts $chart) {}

    /**
     * Creates a society and everything it needs to start operating.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?User $creator = null): Society
    {
        return DB::transaction(function () use ($attributes, $creator) {
            $society = Society::create($attributes + [
                'status' => 'onboarding',
                'created_by' => $creator?->id,
            ]);

            $this->provision($society);

            if ($creator) {
                $this->attachAdministrator($society, $creator);
            }

            return $society->refresh();
        });
    }

    /** Installs defaults. Safe to re-run on an existing society. */
    public function provision(Society $society): void
    {
        $this->syncRolesAndPermissions($society);
        $this->chart->install($society);
        $this->openFinancialYear($society);
        $this->seedChargeHeads($society);
        $this->seedLateFeeRule($society);
        $this->seedComplaintCategories($society);
        $this->applyDefaultSettings($society);
    }

    /**
     * Roles in this system are per-society, so every society gets its own copy
     * of each role with the default permission set attached.
     */
    public function syncRolesAndPermissions(Society $society): void
    {
        foreach (Permission::all() as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($society->id);

        try {
            foreach (RoleName::assignable() as $roleName) {
                $role = RoleModel::findOrCreate($roleName, 'web');

                // Only seed a role's permissions the first time. Re-running
                // must not undo a committee's own adjustments.
                if ($role->permissions()->count() === 0) {
                    $role->syncPermissions(Permission::defaultsForRole($roleName));
                }
            }
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }

    public function attachAdministrator(Society $society, User $user, string $role = RoleName::SOCIETY_ADMIN): void
    {
        $society->users()->syncWithoutDetaching([
            $user->id => ['status' => 'active', 'joined_at' => now()],
        ]);

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($society->id);

        try {
            $user->assignRole($role);
        } finally {
            setPermissionsTeamId($previousTeam);
        }

        if ($user->current_society_id === null) {
            $user->forceFill(['current_society_id' => $society->id])->save();
        }
    }

    /** Opens the financial year that contains today, per the society's calendar. */
    public function openFinancialYear(Society $society, ?Carbon $on = null): FinancialYear
    {
        $on ??= now();
        $startMonth = (int) ($society->financial_year_start_month ?: 4);

        $startYear = $on->month >= $startMonth ? $on->year : $on->year - 1;
        $starts = Carbon::create($startYear, $startMonth, 1)->startOfDay();
        $ends = $starts->copy()->addYear()->subDay()->endOfDay();

        $name = $startMonth === 1
            ? (string) $startYear
            : $startYear.'-'.substr((string) ($startYear + 1), -2);

        $year = FinancialYear::withoutGlobalScopes()->firstOrCreate(
            ['society_id' => $society->id, 'name' => $name],
            [
                'starts_on' => $starts->toDateString(),
                'ends_on' => $ends->toDateString(),
                'is_current' => true,
            ],
        );

        if (! $year->is_current) {
            FinancialYear::withoutGlobalScopes()
                ->where('society_id', $society->id)
                ->update(['is_current' => false]);

            $year->forceFill(['is_current' => true])->save();
        }

        return $year;
    }

    /**
     * The heads almost every society bills or spends under. Rates are left at
     * zero deliberately -- each society sets its own during onboarding.
     */
    private function seedChargeHeads(Society $society): void
    {
        $heads = [
            ['name' => 'Maintenance Charges', 'code' => 'MAINT', 'type' => 'income', 'basis' => 'per_sqft', 'fund' => 'general', 'ledger' => '4000'],
            ['name' => 'Sinking Fund', 'code' => 'SINK', 'type' => 'income', 'basis' => 'per_sqft', 'fund' => 'sinking', 'ledger' => '3100'],
            ['name' => 'Water Charges', 'code' => 'WATER', 'type' => 'income', 'basis' => 'fixed_per_unit', 'fund' => 'general', 'ledger' => '4100'],
            ['name' => 'Parking Charges', 'code' => 'PARK', 'type' => 'income', 'basis' => 'per_vehicle', 'fund' => 'general', 'ledger' => '4200'],
            ['name' => 'Corpus Fund', 'code' => 'CORPUS', 'type' => 'income', 'basis' => 'fixed_per_unit', 'fund' => 'corpus', 'ledger' => '3200', 'recurring' => false],
            ['name' => 'Festival Fund', 'code' => 'FEST', 'type' => 'income', 'basis' => 'fixed_per_unit', 'fund' => 'festival', 'ledger' => '4000', 'recurring' => false],
            ['name' => 'Late Payment Interest', 'code' => 'LATEFEE', 'type' => 'income', 'basis' => 'manual', 'fund' => 'general', 'ledger' => '4900', 'system' => true, 'recurring' => false],
            ['name' => 'Amenity Booking', 'code' => 'AMENITY', 'type' => 'income', 'basis' => 'manual', 'fund' => 'general', 'ledger' => '4300', 'system' => true, 'recurring' => false],
        ];

        foreach ($heads as $i => $head) {
            ChargeHead::withoutGlobalScopes()->firstOrCreate(
                ['society_id' => $society->id, 'code' => $head['code']],
                [
                    'name' => $head['name'],
                    'type' => $head['type'],
                    'basis' => $head['basis'],
                    'fund' => $head['fund'],
                    'default_rate' => 0,
                    'is_recurring' => $head['recurring'] ?? true,
                    'is_system' => $head['system'] ?? false,
                    'is_active' => true,
                    'sort_order' => $i,
                    'ledger_account_id' => $this->chartAccountId($society, $head['ledger']),
                ],
            );
        }
    }

    private function seedLateFeeRule(Society $society): void
    {
        $head = ChargeHead::withoutGlobalScopes()
            ->where('society_id', $society->id)
            ->where('code', 'LATEFEE')
            ->first();

        LateFeeRule::withoutGlobalScopes()->firstOrCreate(
            ['society_id' => $society->id, 'name' => 'Standard late payment interest'],
            [
                'method' => 'percent_per_annum',
                'rate' => 21,           // a common bye-law figure; editable
                'grace_days' => 10,
                'compounding' => 'simple',
                'accrual_frequency' => 'monthly',
                'minimum_amount' => 0,
                'applies_above_amount' => 0,
                'charge_head_id' => $head?->id,
                'is_active' => false,   // off until the committee turns it on
            ],
        );
    }

    private function seedComplaintCategories(Society $society): void
    {
        $categories = [
            ['Plumbing', 'high', 4, 24],
            ['Electrical', 'high', 2, 12],
            ['Lift', 'urgent', 1, 6],
            ['Housekeeping', 'medium', 8, 48],
            ['Security', 'urgent', 1, 4],
            ['Water Supply', 'high', 4, 24],
            ['Common Area', 'medium', 8, 72],
            ['Parking', 'low', 24, 120],
            ['Billing Query', 'medium', 12, 72],
            ['Other', 'low', 24, 120],
        ];

        foreach ($categories as $i => [$name, $priority, $responseSla, $resolutionSla]) {
            ComplaintCategory::withoutGlobalScopes()->firstOrCreate(
                ['society_id' => $society->id, 'name' => $name],
                [
                    'default_priority' => $priority,
                    'response_sla_hours' => $responseSla,
                    'resolution_sla_hours' => $resolutionSla,
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
        }
    }

    private function applyDefaultSettings(Society $society): void
    {
        $defaults = [
            'payments.offline_requires_approval' => true,
            'payments.allow_partial' => true,
            'billing.show_arrears_on_invoice' => true,
            'helpdesk.auto_escalate' => true,
            'helpdesk.escalate_after_hours' => 24,
            'visitors.require_resident_approval' => true,
            'directory.show_phone_to_residents' => false,
            'notices.default_send_email' => false,
        ];

        foreach ($defaults as $key => $value) {
            if ($society->setting($key) === null) {
                $society->putSetting($key, $value);
            }
        }

        $society->save();
    }

    private function chartAccountId(Society $society, string $code): ?int
    {
        return \App\Models\LedgerAccount::withoutGlobalScopes()
            ->where('society_id', $society->id)
            ->where('code', $code)
            ->value('id');
    }

    /** Marks onboarding finished, which unlocks the full navigation. */
    public function completeOnboarding(Society $society): void
    {
        $society->forceFill([
            'status' => 'active',
            'onboarded_at' => now(),
        ])->save();
    }
}
