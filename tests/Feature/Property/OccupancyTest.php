<?php

namespace Tests\Feature\Property;

use App\Models\Unit;
use App\Models\UnitResident;
use App\Services\Property\Occupancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OccupancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_move_out_closes_the_record_rather_than_deleting_it(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $tenant = $this->makeUser($society);

        $occupancy = app(Occupancy::class);

        $residency = $occupancy->moveIn($unit, $tenant, [
            'relation' => 'tenant',
            'start_date' => '2024-04-01',
            'is_billing_contact' => true,
        ]);

        $occupancy->moveOut($residency, Carbon::parse('2026-03-31'), 'Agreement ended');

        $closed = UnitResident::findOrFail($residency->id);

        $this->assertSame('ended', $closed->status);
        $this->assertSame('2026-03-31', $closed->end_date->toDateString());
        $this->assertSame('Agreement ended', $closed->move_out_reason);
        $this->assertSame('2024-04-01', $closed->start_date->toDateString());
    }

    public function test_a_units_status_follows_who_actually_lives_there(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society, ['occupancy_status' => 'vacant']);
        $occupancy = app(Occupancy::class);

        $owner = $this->makeUser($society);
        $occupancy->moveIn($unit, $owner, ['relation' => 'owner']);
        $this->assertSame('owner_occupied', $unit->fresh()->occupancy_status);

        $tenant = $this->makeUser($society);
        $tenancy = $occupancy->moveIn($unit, $tenant, ['relation' => 'tenant']);
        $this->assertSame('rented', $unit->fresh()->occupancy_status);

        $occupancy->moveOut($tenancy);
        $this->assertSame('owner_occupied', $unit->fresh()->occupancy_status);
    }

    public function test_an_empty_unit_reads_as_vacant(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $occupancy = app(Occupancy::class);

        $residency = $occupancy->moveIn($unit, $this->makeUser($society), ['relation' => 'tenant']);
        $occupancy->moveOut($residency);

        $this->assertSame('vacant', $unit->fresh()->occupancy_status);
    }

    public function test_a_unit_under_construction_is_not_relabelled_by_occupancy(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society, ['occupancy_status' => 'under_construction']);

        app(Occupancy::class)->refreshStatus($unit);

        $this->assertSame('under_construction', $unit->fresh()->occupancy_status);
    }

    public function test_bills_never_lose_their_destination_when_someone_leaves(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $occupancy = app(Occupancy::class);

        $owner = $this->makeUser($society);
        $occupancy->moveIn($unit, $owner, ['relation' => 'owner']);

        $tenant = $this->makeUser($society);
        $tenancy = $occupancy->moveIn($unit, $tenant, [
            'relation' => 'tenant',
            'is_billing_contact' => true,
        ]);

        $this->assertSame($tenant->id, $unit->fresh()->billingContact()?->user_id);

        $occupancy->moveOut($tenancy);

        // The owner picks it back up: an unpaid bill is ultimately theirs.
        $this->assertSame($owner->id, $unit->fresh()->billingContact()?->user_id);
    }

    public function test_only_one_person_at_a_time_receives_a_units_bills(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $occupancy = app(Occupancy::class);

        $occupancy->moveIn($unit, $this->makeUser($society), [
            'relation' => 'owner', 'is_billing_contact' => true,
        ]);
        $occupancy->moveIn($unit, $this->makeUser($society), [
            'relation' => 'tenant', 'is_billing_contact' => true,
        ]);

        $this->assertSame(1, UnitResident::where('unit_id', $unit->id)
            ->active()->where('is_billing_contact', true)->count());
    }

    public function test_a_sale_hands_the_unit_over_without_leaving_it_ownerless(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $occupancy = app(Occupancy::class);

        $seller = $this->makeUser($society);
        $occupancy->moveIn($unit, $seller, [
            'relation' => 'owner', 'start_date' => '2019-06-01', 'is_billing_contact' => true,
        ]);

        $buyer = $this->makeUser($society);
        $occupancy->transferOwnership($unit, $buyer, Carbon::parse('2026-01-15'));

        $current = $occupancy->currentFor($unit);

        $this->assertCount(1, $current);
        $this->assertSame($buyer->id, $current->first()->user_id);
        $this->assertTrue($current->first()->is_billing_contact);

        $former = UnitResident::where('user_id', $seller->id)->firstOrFail();
        $this->assertSame('ended', $former->status);
        $this->assertSame('2026-01-15', $former->end_date->toDateString());
        $this->assertSame('Unit sold', $former->move_out_reason);
    }

    public function test_a_unit_keeps_a_readable_history_of_everyone_who_has_lived_there(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $occupancy = app(Occupancy::class);

        $first = $occupancy->moveIn($unit, $this->makeUser($society), [
            'relation' => 'tenant', 'start_date' => '2022-01-01',
        ]);
        $occupancy->moveOut($first, Carbon::parse('2023-12-31'));

        $second = $occupancy->moveIn($unit, $this->makeUser($society), [
            'relation' => 'tenant', 'start_date' => '2024-01-01',
        ]);
        $occupancy->moveOut($second, Carbon::parse('2025-12-31'));

        $occupancy->moveIn($unit, $this->makeUser($society), [
            'relation' => 'tenant', 'start_date' => '2026-01-01',
        ]);

        $history = $occupancy->historyFor($unit);

        $this->assertCount(3, $history);
        $this->assertSame(
            ['2026-01-01', '2024-01-01', '2022-01-01'],
            $history->map(fn ($r) => $r->start_date->toDateString())->all(),
        );
        $this->assertSame(1, $history->where('status', 'active')->count());
    }

    public function test_a_persons_history_follows_them_between_flats(): void
    {
        $society = $this->makeSociety();
        $occupancy = app(Occupancy::class);
        $person = $this->makeUser($society);

        $small = $this->makeUnit($society, ['unit_number' => '101']);
        $large = $this->makeUnit($society, ['unit_number' => '901']);

        $firstStay = $occupancy->moveIn($small, $person, ['relation' => 'tenant', 'start_date' => '2023-01-01']);
        $occupancy->moveOut($firstStay, Carbon::parse('2025-06-30'), 'Moved within the society');
        $occupancy->moveIn($large, $person, ['relation' => 'tenant', 'start_date' => '2025-07-01']);

        $history = $occupancy->historyForUser($person);

        $this->assertCount(2, $history);
        $this->assertSame('901', $history->first()->unit->unit_number);
        $this->assertSame('101', $history->last()->unit->unit_number);
    }

    public function test_a_residency_reads_its_length_the_way_a_person_would_say_it(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $occupancy = app(Occupancy::class);

        $cases = [
            ['2026-01-10', '2026-01-10', 'same day'],
            ['2026-01-01', '2026-01-02', '1 day'],
            ['2026-01-01', '2026-01-15', '14 days'],
            ['2026-01-01', '2026-04-01', '3 months'],
            ['2024-01-01', '2026-01-01', '2 years'],
            ['2024-01-01', '2026-04-01', '2 years 3 months'],
        ];

        foreach ($cases as [$from, $to, $expected]) {
            $residency = $occupancy->moveIn($unit, $this->makeUser($society), [
                'relation' => 'tenant',
                'start_date' => $from,
            ]);

            $closed = $occupancy->moveOut($residency, Carbon::parse($to));

            $this->assertSame($expected, $closed->durationLabel(), "{$from} to {$to}");
        }
    }

    /**
     * `residents` and `activeResidents` are two relations over overlapping
     * rows. A screen that eager loaded one and a helper that read the other
     * produced a lazy-loading violation in production but never in a test,
     * because tests do not run with lazy loading disabled by default.
     */
    public function test_the_billing_contact_is_readable_whichever_relation_was_eager_loaded(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $owner = $this->makeUser($society);

        app(Occupancy::class)->moveIn($unit, $owner, [
            'relation' => 'owner', 'is_billing_contact' => true,
        ]);

        Model::preventLazyLoading();

        try {
            foreach (['residents.user', 'activeResidents.user'] as $relation) {
                $loaded = Unit::with($relation)->findOrFail($unit->id);

                $this->assertSame(
                    $owner->id,
                    $loaded->billingContact()?->user?->id,
                    "Reading the billing contact failed when [{$relation}] was loaded.",
                );
            }

            // And a unit loaded with neither still answers, by querying.
            $bare = Unit::findOrFail($unit->id);
            $this->assertSame($owner->id, $bare->billingContact()?->user?->id);
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_occupancy_history_does_not_leak_between_societies(): void
    {
        $first = $this->makeSociety();
        $firstUnit = $this->makeUnit($first);
        app(Occupancy::class)->moveIn($firstUnit, $this->makeUser($first), ['relation' => 'owner']);

        $second = $this->makeSociety();
        $secondUnit = $this->makeUnit($second);

        $this->assertCount(0, app(Occupancy::class)->historyFor($secondUnit));
        $this->assertSame(0, UnitResident::count());
    }
}
