<?php

namespace Database\Seeders;

use App\Enums\Role as RoleName;
use App\Models\Amenity;
use App\Models\Asset;
use App\Models\BillingPlan;
use App\Models\Block;
use App\Models\ChargeHead;
use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\ComplaintCategory;
use App\Models\EmergencyContact;
use App\Models\Gate;
use App\Models\LateFeeRule;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\Notice;
use App\Models\ParkingSlot;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Society;
use App\Models\Staff;
use App\Models\Unit;
use App\Models\UnitResident;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\LateFeeCalculator;
use App\Services\Helpdesk\ComplaintService;
use App\Services\Payments\PaymentRecorder;
use App\Services\SocietyProvisioner;
use App\Support\SocietyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Builds two very different societies so the platform can be explored without
 * setting anything up: an apartment complex billed per square foot, and a
 * villa project billed a flat amount per villa.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $context = app(SocietyContext::class);
        $provisioner = app(SocietyProvisioner::class);

        $superAdmin = User::updateOrCreate(
            ['email' => 'super@sankul.test'],
            [
                'name' => 'Platform Operator',
                'password' => 'password',
                'phone' => '9000000000',
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $apartment = $this->buildApartment($provisioner, $context, $superAdmin);
        $this->buildVillaProject($provisioner, $context, $superAdmin);

        // Leave the operator pointed at the richer society.
        $superAdmin->forceFill(['current_society_id' => $apartment->id])->save();

        $context->forget();

        $this->command?->newLine();
        $this->command?->info('Demo data ready. Sign in with any of these (password: password):');
        $this->command?->table(
            ['Role', 'Email'],
            [
                ['Platform operator', 'super@sankul.test'],
                ['Society admin / treasurer', 'treasurer@sankul.test'],
                ['Secretary', 'secretary@sankul.test'],
                ['Manager', 'manager@sankul.test'],
                ['Security guard', 'guard@sankul.test'],
                ['Resident (owner)', 'owner@sankul.test'],
                ['Resident (tenant)', 'tenant@sankul.test'],
            ],
        );
    }

    private function buildApartment(SocietyProvisioner $provisioner, SocietyContext $context, User $creator): Society
    {
        $society = $provisioner->create([
            'name' => 'Shreeji Residency',
            'type' => 'apartment',
            'registration_number' => 'GUJ/SUR/HSG/2016/4412',
            'address_line1' => 'Near Vesu Circle, Vesu',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'postal_code' => '395007',
            'contact_email' => 'office@shreejiresidency.test',
            'contact_phone' => '0261 2345678',
            'area_unit' => 'sqft',
            'financial_year_start_month' => 4,
            'payment_mode' => 'offline',
            'status' => 'active',
            'onboarded_at' => now(),
        ], $creator);

        $context->set($society);
        setPermissionsTeamId($society->id);

        $people = $this->createPeople($society, $provisioner);

        // --- structure --------------------------------------------------
        $blocks = collect(['A', 'B', 'C'])->map(fn ($name) => Block::create([
            'name' => $name,
            'kind' => 'wing',
            'floor_count' => 6,
        ]));

        $units = collect();
        $areas = [875, 1150, 1150, 1425];

        foreach ($blocks as $block) {
            foreach (range(1, 6) as $floor) {
                foreach (range(1, 4) as $position) {
                    $units->push(Unit::create([
                        'block_id' => $block->id,
                        'unit_number' => ($floor * 100) + $position,
                        'floor' => (string) $floor,
                        'type' => 'flat',
                        'configuration' => $areas[$position - 1] > 1200 ? '3BHK' : '2BHK',
                        'bedrooms' => $areas[$position - 1] > 1200 ? 3 : 2,
                        'carpet_area' => $areas[$position - 1],
                        'built_up_area' => $areas[$position - 1] * 1.2,
                        'super_built_up_area' => $areas[$position - 1] * 1.4,
                        'occupancy_status' => $this->occupancyFor($position),
                        'is_billable' => true,
                    ]));
                }
            }
        }

        $this->attachResidents($society, $units, $people);
        $this->createParking($society, $blocks, $units);

        // --- billing ----------------------------------------------------
        $this->configureCharges($society, [
            'MAINT' => ['rate' => 2.40, 'basis' => 'per_sqft'],
            'SINK' => ['rate' => 0.60, 'basis' => 'per_sqft'],
            'WATER' => ['rate' => 250, 'basis' => 'fixed_per_unit'],
            'PARK' => ['rate' => 150, 'basis' => 'per_vehicle'],
        ]);

        $plan = $this->createPlan($society, ['MAINT', 'SINK', 'WATER', 'PARK'], 'Monthly maintenance');

        $this->runHistoricBilling($plan, $people['treasurer'], months: 4);

        // --- operations -------------------------------------------------
        $this->createVendorsAndAssets($society);
        $this->createAmenities($society);
        $this->createStaff($society);
        $this->createHelpdeskTickets($society, $units, $people);
        $this->createGovernance($society, $people);
        $this->createNotices($society, $people);
        $this->createEmergencyContacts($society);

        Gate::create(['name' => 'Main Gate', 'code' => 'MG', 'type' => 'main']);
        Gate::create(['name' => 'Service Gate', 'code' => 'SG', 'type' => 'service']);

        return $society;
    }

    private function buildVillaProject(SocietyProvisioner $provisioner, SocietyContext $context, User $creator): Society
    {
        $society = $provisioner->create([
            'name' => 'Palm Grove Villas',
            'type' => 'gated_community',
            'address_line1' => 'Sarjapur Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'postal_code' => '560035',
            'area_unit' => 'sqft',
            'financial_year_start_month' => 4,
            'payment_mode' => 'offline',
            'status' => 'active',
            'onboarded_at' => now(),
        ], $creator);

        $context->set($society);
        setPermissionsTeamId($society->id);

        $admin = User::updateOrCreate(
            ['email' => 'palmgrove@sankul.test'],
            ['name' => 'Latha Rao', 'password' => 'password', 'phone' => '9820011223', 'email_verified_at' => now()],
        );
        $provisioner->attachAdministrator($society, $admin, RoleName::PRESIDENT);

        $units = collect(range(1, 16))->map(fn ($n) => Unit::create([
            'unit_number' => 'V-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'type' => 'villa',
            'configuration' => '4BHK',
            'bedrooms' => 4,
            'carpet_area' => 2400 + ($n % 3) * 200,
            'occupancy_status' => $n % 5 === 0 ? 'vacant' : 'owner_occupied',
        ]));

        // A flat charge per villa, which is how many villa projects bill.
        $this->configureCharges($society, [
            'MAINT' => ['rate' => 6500, 'basis' => 'fixed_per_unit'],
            'SINK' => ['rate' => 1500, 'basis' => 'fixed_per_unit'],
        ]);

        $plan = $this->createPlan($society, ['MAINT', 'SINK'], 'Quarterly maintenance', 'quarterly', 30);
        $this->runHistoricBilling($plan, $admin, months: 3);

        Notice::create([
            'title' => 'Borewell servicing on Sunday',
            'body' => "The borewell pump will be serviced this Sunday between 9 AM and 1 PM.\n\nWater supply to all villas will be interrupted during this window. Please store what you need the night before.",
            'category' => 'maintenance',
            'priority' => 'important',
            'audience' => 'all',
            'status' => 'published',
            'published_at' => now()->subDays(2),
            'created_by' => $admin->id,
        ]);

        return $society;
    }

    // --- helpers ---------------------------------------------------------

    private function occupancyFor(int $position): string
    {
        return match ($position) {
            1 => 'owner_occupied',
            2 => 'rented',
            3 => 'owner_occupied',
            default => 'vacant',
        };
    }

    /** @return array<string, User> */
    private function createPeople(Society $society, SocietyProvisioner $provisioner): array
    {
        $definitions = [
            'treasurer' => ['Jigar Surati', 'treasurer@sankul.test', '9825011111', RoleName::SOCIETY_ADMIN],
            'secretary' => ['Meera Shah', 'secretary@sankul.test', '9825022222', RoleName::SECRETARY],
            'president' => ['Rakesh Patel', 'president@sankul.test', '9825033333', RoleName::PRESIDENT],
            'manager' => ['Sunil Kumar', 'manager@sankul.test', '9825044444', RoleName::MANAGER],
            'guard' => ['Ramesh Yadav', 'guard@sankul.test', '9825055555', RoleName::SECURITY_GUARD],
            'owner' => ['Anita Desai', 'owner@sankul.test', '9825066666', RoleName::OWNER],
            'tenant' => ['Kiran Joshi', 'tenant@sankul.test', '9825077777', RoleName::TENANT],
        ];

        $people = [];

        foreach ($definitions as $key => [$name, $email, $phone, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'phone' => $phone, 'email_verified_at' => now()],
            );

            $provisioner->attachAdministrator($society, $user, $role);
            $people[$key] = $user;
        }

        // The treasurer also holds the treasurer role alongside admin.
        $people['treasurer']->assignRole(RoleName::TREASURER);

        return $people;
    }

    private function attachResidents(Society $society, $units, array $people): void
    {
        $names = [
            'Bhavesh Mehta', 'Priya Nair', 'Arun Verma', 'Sneha Kapoor', 'Vikram Rathod',
            'Nisha Gupta', 'Hardik Trivedi', 'Pooja Shah', 'Manish Agarwal', 'Rina Bhatt',
            'Sameer Khan', 'Divya Menon', 'Ajay Chauhan', 'Kavita Rao', 'Nilesh Parmar',
            'Shruti Iyer', 'Gaurav Singh', 'Alka Jain', 'Rohit Desai', 'Tanvi Sheth',
        ];

        $index = 0;

        foreach ($units as $position => $unit) {
            if ($unit->occupancy_status === 'vacant') {
                // A vacant flat still has an owner who is billed for it.
                $owner = $this->residentUser($names[$index % count($names)].' ', $index);
                $index++;

                UnitResident::create([
                    'unit_id' => $unit->id,
                    'user_id' => $owner->id,
                    'relation' => 'owner',
                    'is_primary' => true,
                    'is_billing_contact' => true,
                    'start_date' => now()->subYears(2),
                ]);

                $society->users()->syncWithoutDetaching([$owner->id => ['status' => 'active', 'joined_at' => now()]]);
                setPermissionsTeamId($society->id);
                $owner->assignRole(RoleName::OWNER);

                continue;
            }

            // The two named demo residents live in the first two flats, so
            // signing in as them lands on a populated account.
            $user = match ($position) {
                0 => $people['owner'],
                1 => $people['tenant'],
                default => $this->residentUser($names[$index % count($names)], $index),
            };

            if ($position > 1) {
                $index++;
                $society->users()->syncWithoutDetaching([$user->id => ['status' => 'active', 'joined_at' => now()]]);
                setPermissionsTeamId($society->id);
                $user->assignRole($unit->occupancy_status === 'rented' ? RoleName::TENANT : RoleName::OWNER);
            }

            UnitResident::create([
                'unit_id' => $unit->id,
                'user_id' => $user->id,
                'relation' => $unit->occupancy_status === 'rented' ? 'tenant' : 'owner',
                'is_primary' => true,
                'is_billing_contact' => true,
                'start_date' => now()->subMonths(random_int(6, 40)),
                'agreement_start_date' => $unit->occupancy_status === 'rented' ? now()->subMonths(8) : null,
                'agreement_end_date' => $unit->occupancy_status === 'rented' ? now()->addMonths(random_int(1, 16)) : null,
                'rent_amount' => $unit->occupancy_status === 'rented' ? random_int(14, 26) * 1000 : null,
                'police_verification_done' => $unit->occupancy_status === 'rented',
            ]);
        }
    }

    private function residentUser(string $name, int $index): User
    {
        return User::updateOrCreate(
            ['email' => Str::slug($name).'.'.$index.'@sankul.test'],
            [
                'name' => trim($name),
                'password' => 'password',
                'phone' => '98' . str_pad((string) (10000000 + $index * 137), 8, '0', STR_PAD_LEFT),
                'email_verified_at' => now(),
            ],
        );
    }

    private function createParking(Society $society, $blocks, $units): void
    {
        $withVehicles = $units->where('occupancy_status', '!=', 'vacant')->take(40);

        foreach ($withVehicles as $i => $unit) {
            $slot = ParkingSlot::create([
                'block_id' => $unit->block_id,
                'unit_id' => $unit->id,
                'code' => 'P-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'level' => $i % 3 === 0 ? 'basement' : 'stilt',
                'vehicle_type' => 'car',
                'monthly_charge' => 0,
                'status' => 'allotted',
                'allotted_on' => now()->subYear(),
            ]);

            \App\Models\Vehicle::create([
                'unit_id' => $unit->id,
                'parking_slot_id' => $slot->id,
                'registration_number' => sprintf('GJ05%s%04d', chr(65 + ($i % 26)), 1000 + $i),
                'type' => 'car',
                'make_model' => ['Maruti Swift', 'Hyundai Creta', 'Tata Nexon', 'Honda City'][$i % 4],
                'colour' => ['White', 'Silver', 'Blue', 'Grey'][$i % 4],
            ]);
        }

        foreach (range(1, 6) as $n) {
            ParkingSlot::create([
                'code' => 'VIS-'.$n,
                'level' => 'open',
                'vehicle_type' => 'any',
                'is_visitor_slot' => true,
                'status' => 'vacant',
            ]);
        }
    }

    private function configureCharges(Society $society, array $config): void
    {
        foreach ($config as $code => $settings) {
            ChargeHead::where('code', $code)->first()?->forceFill([
                'default_rate' => $settings['rate'],
                'basis' => $settings['basis'],
                'is_active' => true,
            ])->save();
        }
    }

    private function createPlan(Society $society, array $codes, string $name, string $cycle = 'monthly', int $dueAfter = 15): BillingPlan
    {
        $rule = LateFeeRule::first();
        $rule?->forceFill(['is_active' => true])->save();

        $plan = BillingPlan::create([
            'name' => $name,
            'cycle' => $cycle,
            'due_after_days' => $dueAfter,
            'starts_on' => now()->subMonths(6)->startOfMonth()->toDateString(),
            'next_run_on' => now()->subMonths(6)->startOfMonth()->toDateString(),
            'auto_generate' => true,
            'auto_issue' => true,
            'is_active' => true,
            'late_fee_rule_id' => $rule?->id,
        ]);

        $heads = ChargeHead::whereIn('code', $codes)->get();
        $plan->chargeHeads()->sync(
            $heads->mapWithKeys(fn ($h, $i) => [$h->id => ['sort_order' => $i]])->all()
        );

        return $plan->load('chargeHeads', 'society');
    }

    /**
     * Runs several past billing periods and settles most of them, so the
     * dashboards and reports have a realistic collection history rather than
     * a single month of pristine data.
     */
    private function runHistoricBilling(BillingPlan $plan, User $actor, int $months): void
    {
        $generator = app(InvoiceGenerator::class);
        $recorder = app(PaymentRecorder::class);
        $lateFees = app(LateFeeCalculator::class);
        $rule = LateFeeRule::first();

        $step = max(1, $plan->cycleMonths());

        for ($i = $months; $i >= 0; $i--) {
            // The newest run is dated today so its bills are not yet due;
            // earlier ones sit at the start of their period.
            $on = $i === 0
                ? now()->copy()
                : now()->copy()->subMonths($i * $step)->startOfMonth();

            $plan->forceFill(['next_run_on' => $on->toDateString()])->save();
            $result = $generator->run($plan->fresh('chargeHeads', 'society'), $on, $actor->id);

            foreach ($result['invoices'] as $index => $invoice) {
                // Older bills are settled more often than recent ones, and a
                // slice is always left unpaid so the defaulter list is real.
                $settleChance = $i >= 2 ? 88 : ($i === 1 ? 70 : 45);

                if (($index * 7 + $i * 13) % 100 >= $settleChance) {
                    continue;
                }

                $payment = $recorder->recordOffline($invoice->unit, [
                    'amount' => (float) $invoice->total,
                    'method' => ['cash', 'upi', 'neft', 'cheque'][$index % 4],
                    'paid_at' => $invoice->due_date->copy()->subDays(random_int(0, 8)),
                    'reference_number' => 'DEMO-'.Str::upper(Str::random(6)),
                    'invoice_ids' => [$invoice->id],
                ], $actor);

                if ($payment->needsApproval()) {
                    $recorder->approve($payment, $actor, [$invoice->id]);
                }
            }
        }

        // Accrue interest on whatever is genuinely overdue.
        if ($rule) {
            $lateFees->accrueForSociety($plan->society, $rule->fresh());
        }
    }

    private function createVendorsAndAssets(Society $society): void
    {
        $vendors = [
            ['Shakti Lift Services', 'Lift maintenance', 'Deepak Shah', '9824100001'],
            ['CleanPro Facility', 'Housekeeping', 'Farida Sheikh', '9824100002'],
            ['SecureGuard Services', 'Security', 'Ajay Rawat', '9824100003'],
            ['AquaFlow Plumbing', 'Plumbing', 'Mahesh Patel', '9824100004'],
        ];

        $created = collect($vendors)->map(fn ($v) => Vendor::create([
            'name' => $v[0],
            'category' => $v[1],
            'contact_person' => $v[2],
            'phone' => $v[3],
            'contract_start' => now()->subYear(),
            'contract_end' => now()->addMonths(random_int(1, 10)),
            'is_active' => true,
        ]));

        $assets = [
            ['Passenger Lift — A Wing', 'lift', 'A Wing lobby'],
            ['Passenger Lift — B Wing', 'lift', 'B Wing lobby'],
            ['Diesel Generator 125 kVA', 'generator', 'Basement'],
            ['Borewell Pump', 'water_pump', 'Pump room'],
            ['CCTV System (24 cameras)', 'cctv', 'Across the complex'],
            ['Fire Fighting System', 'fire_safety', 'All floors'],
        ];

        foreach ($assets as $i => [$name, $category, $location]) {
            $asset = Asset::create([
                'name' => $name,
                'code' => 'AST-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'category' => $category,
                'location' => $location,
                'vendor_id' => $created[$i % $created->count()]->id,
                'purchase_date' => now()->subYears(random_int(2, 8)),
                'purchase_cost' => random_int(2, 25) * 100000,
                'warranty_expires_on' => now()->addMonths(random_int(-24, 18)),
                'condition' => ['excellent', 'good', 'good', 'fair'][$i % 4],
                'status' => $i === 3 ? 'under_repair' : 'active',
            ]);

            \App\Models\AmcContract::create([
                'asset_id' => $asset->id,
                'vendor_id' => $asset->vendor_id,
                'contract_number' => 'AMC-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'title' => 'Annual maintenance — '.$name,
                'start_date' => now()->subMonths(6),
                'end_date' => now()->addMonths(6),
                'amount' => random_int(15, 90) * 1000,
                'payment_frequency' => 'yearly',
                'status' => 'active',
            ]);
        }
    }

    private function createAmenities(Society $society): void
    {
        $amenities = [
            ['Community Hall', 'community_hall', 150, 2500, 'per_slot', true],
            ['Gymnasium', 'gym', 25, 0, 'free', false],
            ['Swimming Pool', 'swimming_pool', 40, 0, 'free', false],
            ['Terrace Party Area', 'terrace', 80, 1500, 'per_slot', true],
            ['Guest Room', 'guest_room', 4, 800, 'per_day', true],
        ];

        foreach ($amenities as [$name, $type, $capacity, $charge, $basis, $approval]) {
            Amenity::create([
                'name' => $name,
                'type' => $type,
                'capacity' => $capacity,
                'charge_amount' => $charge,
                'charge_basis' => $basis,
                'deposit_amount' => $charge > 0 ? 2000 : 0,
                'requires_approval' => $approval,
                'min_booking_minutes' => 60,
                'max_booking_minutes' => 480,
                'advance_booking_days' => 45,
                'max_active_bookings_per_unit' => 2,
                'opens_at' => '06:00:00',
                'closes_at' => '22:00:00',
                'available_days' => [1, 2, 3, 4, 5, 6, 7],
                'rules' => "Leave the space as you found it.\nNo amplified music after 10 PM.",
                'is_active' => true,
                'is_bookable' => true,
            ]);
        }
    }

    private function createStaff(Society $society): void
    {
        $staff = [
            ['Ramesh Yadav', 'security', 'Head Guard', '9824200001'],
            ['Suresh Pawar', 'security', 'Security Guard', '9824200002'],
            ['Lata Devi', 'housekeeping', 'Housekeeping Supervisor', '9824200003'],
            ['Ganesh More', 'maintenance', 'Electrician', '9824200004'],
            ['Kishore Bhai', 'gardening', 'Gardener', '9824200005'],
            ['Sunil Kumar', 'administration', 'Facility Manager', '9825044444'],
        ];

        foreach ($staff as $i => [$name, $department, $designation, $phone]) {
            Staff::create([
                'employee_code' => 'EMP-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'phone' => $phone,
                'department' => $department,
                'designation' => $designation,
                'employment_type' => $i < 2 ? 'outsourced' : 'permanent',
                'joined_on' => now()->subMonths(random_int(6, 48)),
                'monthly_salary' => random_int(12, 35) * 1000,
                'police_verified' => $i !== 4,
                'police_verified_on' => $i !== 4 ? now()->subMonths(random_int(2, 20)) : null,
                'status' => 'active',
            ]);
        }
    }

    private function createHelpdeskTickets(Society $society, $units, array $people): void
    {
        $helpdesk = app(ComplaintService::class);

        $tickets = [
            ['Lift in A wing stops between floors', 'Lift', 'urgent', 'A Wing lobby', 'resolved'],
            ['No water supply on the 4th floor since morning', 'Water Supply', 'high', '4th floor, B wing', 'in_progress'],
            ['Corridor light not working', 'Electrical', 'medium', '2nd floor corridor', 'open'],
            ['Leakage from the flat above', 'Plumbing', 'high', 'Bathroom ceiling', 'assigned'],
            ['Garbage not collected for two days', 'Housekeeping', 'medium', 'Ground floor bins', 'resolved'],
            ['Visitor parking occupied by a resident', 'Parking', 'low', 'Visitor bay', 'open'],
            ['Gate light fused', 'Electrical', 'low', 'Main gate', 'closed'],
        ];

        foreach ($tickets as $i => [$title, $categoryName, $priority, $location, $finalStatus]) {
            $category = ComplaintCategory::where('name', $categoryName)->first();
            $unit = $units[$i * 3] ?? $units->first();

            $complaint = $helpdesk->raise($society, [
                'title' => $title,
                'description' => "Reported by a resident.\n\n".$title.'. Please look into it at the earliest.',
                'location' => $location,
                'complaint_category_id' => $category?->id,
                'priority' => $priority,
                'unit_id' => $unit->id,
                'is_public' => in_array($categoryName, ['Lift', 'Water Supply', 'Housekeeping'], true),
            ], $people['owner']);

            // Backdate so the SLA figures on the dashboard mean something.
            $raisedAt = now()->subDays(random_int(1, 20));
            $complaint->forceFill(['created_at' => $raisedAt])->save();

            if ($finalStatus !== 'open') {
                $helpdesk->assign($complaint, $people['manager'], $people['secretary']);
            }

            if (in_array($finalStatus, ['in_progress', 'resolved', 'closed'], true)) {
                $helpdesk->comment($complaint, $people['manager'], 'Looking into this now.', false);
                $helpdesk->changeStatus($complaint, 'in_progress', $people['manager']);
            }

            if (in_array($finalStatus, ['resolved', 'closed'], true)) {
                $helpdesk->changeStatus($complaint, 'resolved', $people['manager'], 'Attended and fixed.');

                // Resolved a few hours after it was raised, not a few weeks,
                // so the average-resolution figure is believable.
                $complaint->forceFill([
                    'first_responded_at' => $raisedAt->copy()->addHours(random_int(1, 4)),
                    'resolved_at' => $raisedAt->copy()->addHours(random_int(5, 40)),
                ])->save();

                $helpdesk->rate($complaint, random_int(3, 5), 'Sorted quickly, thank you.');
            }

            if ($finalStatus === 'closed') {
                $helpdesk->changeStatus($complaint, 'closed', $people['secretary']);
            }
        }
    }

    private function createGovernance(Society $society, array $people): void
    {
        $committee = Committee::create([
            'name' => 'Managing Committee 2026-27',
            'term_start' => now()->subMonths(4),
            'term_end' => now()->addMonths(8),
            'is_active' => true,
        ]);

        foreach ([
            [$people['president'], 'president'],
            [$people['secretary'], 'secretary'],
            [$people['treasurer'], 'treasurer'],
        ] as [$user, $designation]) {
            CommitteeMember::create([
                'committee_id' => $committee->id,
                'user_id' => $user->id,
                'designation' => $designation,
                'from_date' => now()->subMonths(4),
                'is_active' => true,
            ]);
        }

        $agm = Meeting::create([
            'title' => 'Annual General Meeting 2026-27',
            'type' => 'agm',
            'description' => 'Adoption of accounts, budget approval and committee elections.',
            'scheduled_at' => now()->addDays(18)->setTime(18, 30),
            'ends_at' => now()->addDays(18)->setTime(20, 30),
            'venue' => 'Community Hall, Ground Floor',
            'mode' => 'physical',
            'notice_days' => 14,
            'quorum_required' => 25,
            'status' => 'scheduled',
            'audience' => 'all_members',
            'created_by' => $people['secretary']->id,
        ]);

        foreach ([
            'Adoption of the audited accounts for the year',
            'Approval of the maintenance budget for the coming year',
            'Revision of the maintenance rate per square foot',
            'Lift modernisation proposal for A and B wings',
            'Election of the managing committee',
        ] as $i => $title) {
            MeetingAgendaItem::create([
                'meeting_id' => $agm->id,
                'sort_order' => $i,
                'title' => $title,
                'proposed_by' => $people['secretary']->id,
            ]);
        }

        $past = Meeting::create([
            'title' => 'Committee Meeting — Monsoon Preparedness',
            'type' => 'committee',
            'scheduled_at' => now()->subDays(22)->setTime(19, 0),
            'venue' => 'Society Office',
            'mode' => 'physical',
            'notice_days' => 3,
            'quorum_required' => 3,
            'quorum_met' => true,
            'status' => 'completed',
            'minutes' => "The committee reviewed monsoon preparation.\n\n"
                ."1. Terrace waterproofing to be completed before 15 June. Quotes received from three vendors; AquaFlow Plumbing selected.\n"
                ."2. Drain cleaning scheduled for the first week of June.\n"
                ."3. Diesel generator to be serviced and the fuel tank topped up.\n"
                ."4. Residents to be reminded to clear their own balcony drains.",
            'minutes_recorded_by' => $people['secretary']->id,
            'minutes_published_at' => now()->subDays(20),
            'created_by' => $people['secretary']->id,
        ]);

        $poll = Poll::create([
            'title' => 'Should we install rooftop solar panels?',
            'description' => 'An estimated ₹18 lakh investment, expected to cut common-area electricity costs by around 60%. The sinking fund would cover it.',
            'type' => 'single_choice',
            'voting_basis' => 'per_unit',
            'eligibility' => 'owners_only',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addDays(11),
            'is_anonymous' => false,
            'show_results_before_close' => false,
            'status' => 'open',
            'created_by' => $people['secretary']->id,
        ]);

        foreach (['Yes, proceed', 'No, not now', 'Abstain'] as $i => $label) {
            PollOption::create(['poll_id' => $poll->id, 'label' => $label, 'sort_order' => $i]);
        }
    }

    private function createNotices(Society $society, array $people): void
    {
        $notices = [
            [
                'Annual General Meeting — 18 days from now',
                "The AGM will be held in the Community Hall.\n\nThe agenda, audited accounts and the proposed budget are in the Documents section. Please come prepared with your questions.\n\nIf you cannot attend, you may appoint a proxy.",
                'meeting', 'important', true,
            ],
            [
                'Water tank cleaning on Saturday',
                "All overhead tanks will be cleaned this Saturday between 10 AM and 4 PM.\n\nSupply will be interrupted during this period. Please store water for the day.",
                'maintenance', 'important', false,
            ],
            [
                'Maintenance dues for this quarter',
                "Invoices for the current period have been issued and are visible under Invoices.\n\nPlease clear your dues before the due date to avoid interest. If you have already paid, your receipt is available for download.",
                'financial', 'normal', false,
            ],
            [
                'Visitor parking is not for residents',
                "Several residents have been parking in the visitor bays.\n\nPlease use your allotted slot. Vehicles found in visitor bays without a pass will be noted at the gate.",
                'general', 'normal', false,
            ],
        ];

        foreach ($notices as $i => [$title, $body, $category, $priority, $pinned]) {
            Notice::create([
                'title' => $title,
                'body' => $body,
                'category' => $category,
                'priority' => $priority,
                'audience' => 'all',
                'is_pinned' => $pinned,
                'status' => 'published',
                'published_at' => now()->subDays($i * 3 + 1),
                'created_by' => $people['secretary']->id,
            ]);
        }
    }

    private function createEmergencyContacts(Society $society): void
    {
        $contacts = [
            ['Police Control Room', 'Emergency', '100', 'police'],
            ['Fire Brigade', 'Emergency', '101', 'fire'],
            ['Ambulance', 'Emergency', '108', 'ambulance'],
            ['Society Office', 'Facility Manager', '02612345678', 'management'],
            ['Main Gate', 'Security Desk', '9824200001', 'security'],
            ['Shakti Lift Services', '24x7 Lift Helpline', '9824100001', 'lift_service'],
        ];

        foreach ($contacts as $i => [$name, $designation, $phone, $category]) {
            EmergencyContact::create([
                'name' => $name,
                'designation' => $designation,
                'phone' => $phone,
                'category' => $category,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }
    }
}
