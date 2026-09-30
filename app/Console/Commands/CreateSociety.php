<?php

namespace App\Console\Commands;

use App\Enums\Role as RoleName;
use App\Models\ChargeHead;
use App\Models\LedgerAccount;
use App\Models\Society;
use App\Models\User;
use App\Services\SocietyProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Creates a society from the command line.
 *
 * The same job the platform console does, for setting up the very first
 * society on a fresh installation -- when there is no operator account yet to
 * sign in with -- and for scripted provisioning.
 */
class CreateSociety extends Command
{
    protected $signature = 'society:create
        {--name= : The society name}
        {--type=apartment : apartment, villa, gated_community, township, commercial_complex, ...}
        {--city=}
        {--admin-name= : The first administrator}
        {--admin-email=}
        {--admin-password= : Left blank, a random one is set}
        {--area-unit=sqft : sqft, sqm or sqyd}
        {--fy-start=4 : Month the financial year starts, 1-12}
        {--payment-mode=offline : offline, online or both}
        {--super-admin : Also make the administrator a platform operator}';

    protected $description = 'Create a society and its first administrator';

    public function handle(SocietyProvisioner $provisioner): int
    {
        $name = $this->option('name') ?: text(
            label: 'What is the society called?',
            required: true,
        );

        $type = $this->option('type');

        if (! $this->option('name')) {
            $type = select(
                label: 'What kind of community is it?',
                options: [
                    'apartment' => 'Apartment complex',
                    'villa' => 'Villa project',
                    'row_house' => 'Row houses',
                    'gated_community' => 'Gated community',
                    'township' => 'Township',
                    'plotted_development' => 'Plotted development',
                    'commercial_complex' => 'Commercial complex',
                    'cooperative_housing' => 'Co-operative housing society',
                    'other' => 'Other',
                ],
                default: 'apartment',
            );
        }

        $adminName = $this->option('admin-name') ?: text('Administrator name', required: true);
        $adminEmail = $this->option('admin-email') ?: text('Administrator email', required: true);
        $password = $this->option('admin-password') ?: Str::random(16);
        $generated = ! $this->option('admin-password');

        if (User::where('email', $adminEmail)->exists() && $generated) {
            // Reusing an existing account keeps their current password.
            $generated = false;
        }

        $society = DB::transaction(function () use ($name, $type, $adminName, $adminEmail, $password, $provisioner) {
            $society = $provisioner->create([
                'name' => $name,
                'type' => $type,
                'city' => $this->option('city') ?: null,
                'area_unit' => $this->option('area-unit'),
                'financial_year_start_month' => (int) $this->option('fy-start'),
                'payment_mode' => $this->option('payment-mode'),
            ]);

            $admin = User::firstOrCreate(
                ['email' => $adminEmail],
                ['name' => $adminName, 'password' => $password],
            );

            if ($this->option('super-admin')) {
                $admin->forceFill(['is_super_admin' => true])->save();
            }

            $provisioner->attachAdministrator($society, $admin, RoleName::SOCIETY_ADMIN);

            return $society;
        });

        $this->newLine();
        $this->info("Created {$society->name} ({$society->code}).");
        $this->table(['Setting', 'Value'], [
            ['Type', $society->typeLabel()],
            ['Financial year', $society->currentFinancialYear()?->name ?? '–'],
            ['Collection', ucfirst($society->payment_mode)],
            ['Charge heads', ChargeHead::withoutGlobalScopes()->where('society_id', $society->id)->count()],
            ['Ledger accounts', LedgerAccount::withoutGlobalScopes()->where('society_id', $society->id)->count()],
            ['Administrator', $adminEmail],
            ['Password', $generated ? $password : '(unchanged)'],
        ]);

        $this->newLine();
        $this->line('Sign in as the administrator and finish setup at /onboarding.');

        return self::SUCCESS;
    }
}
