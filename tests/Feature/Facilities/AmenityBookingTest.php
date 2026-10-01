<?php

namespace Tests\Feature\Facilities;

use App\Models\Amenity;
use App\Services\Facilities\AmenityBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AmenityBookingTest extends TestCase
{
    use RefreshDatabase;

    private function amenity(array $attributes = []): Amenity
    {
        return Amenity::create(array_merge([
            'name' => 'Community Hall',
            'type' => 'community_hall',
            'capacity' => 100,
            'charge_amount' => 1000,
            'charge_basis' => 'per_slot',
            'is_bookable' => true,
            'is_active' => true,
            'requires_approval' => false,
            'min_booking_minutes' => 60,
            'max_booking_minutes' => 480,
            'advance_booking_days' => 30,
            'min_notice_hours' => 2,
            'max_active_bookings_per_unit' => 2,
            'available_days' => [1, 2, 3, 4, 5, 6, 7],
        ], $attributes));
    }

    public function test_a_valid_slot_can_be_booked(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $user = $this->makeUser($society);
        $amenity = $this->amenity();

        $start = now()->addDays(3)->setTime(10, 0);
        $end = $start->copy()->addHours(3);

        $booking = app(AmenityBookingService::class)->book($amenity, $unit, $start, $end, $user);

        $this->assertSame('approved', $booking->status);
        $this->assertSame('1000.00', (string) $booking->charge_amount);
    }

    public function test_an_overlapping_slot_is_refused(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $user = $this->makeUser($society);
        $amenity = $this->amenity();
        $service = app(AmenityBookingService::class);

        $start = now()->addDays(3)->setTime(10, 0);
        $service->book($amenity, $unit, $start, $start->copy()->addHours(4), $user);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already booked');

        // Starts inside the existing booking.
        $service->book($amenity, $this->makeUnit($society), $start->copy()->addHour(), $start->copy()->addHours(5), $user);
    }

    public function test_a_back_to_back_slot_is_allowed(): void
    {
        $society = $this->makeSociety();
        $user = $this->makeUser($society);
        $amenity = $this->amenity();
        $service = app(AmenityBookingService::class);

        $start = now()->addDays(3)->setTime(10, 0);
        $service->book($amenity, $this->makeUnit($society), $start, $start->copy()->addHours(2), $user);

        $second = $service->book(
            $amenity,
            $this->makeUnit($society),
            $start->copy()->addHours(2),
            $start->copy()->addHours(4),
            $user,
        );

        $this->assertSame('approved', $second->status);
    }

    public function test_booking_too_far_ahead_is_refused(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $user = $this->makeUser($society);
        $amenity = $this->amenity(['advance_booking_days' => 7]);

        $start = now()->addDays(30)->setTime(10, 0);

        $errors = app(AmenityBookingService::class)
            ->validate($amenity, $unit, $start, $start->copy()->addHours(2));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('7 days ahead', implode(' ', $errors));
    }

    public function test_a_booking_shorter_than_the_minimum_is_refused(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $amenity = $this->amenity(['min_booking_minutes' => 120]);

        $start = now()->addDays(3)->setTime(10, 0);

        $errors = app(AmenityBookingService::class)
            ->validate($amenity, $unit, $start, $start->copy()->addMinutes(30));

        $this->assertStringContainsString('minimum booking is 120', implode(' ', $errors));
    }

    public function test_a_unit_cannot_exceed_its_concurrent_booking_cap(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $user = $this->makeUser($society);
        $amenity = $this->amenity(['max_active_bookings_per_unit' => 1]);
        $service = app(AmenityBookingService::class);

        $start = now()->addDays(3)->setTime(10, 0);
        $service->book($amenity, $unit, $start, $start->copy()->addHours(2), $user);

        $errors = $service->validate(
            $amenity,
            $unit,
            $start->copy()->addDays(1),
            $start->copy()->addDays(1)->addHours(2),
        );

        $this->assertStringContainsString('already has 1 active bookings', implode(' ', $errors));
    }

    public function test_a_blackout_blocks_the_period(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $amenity = $this->amenity();

        $start = now()->addDays(3)->setTime(10, 0);

        $amenity->blackouts()->create([
            'society_id' => $society->id,
            'starts_at' => $start->copy()->subHour(),
            'ends_at' => $start->copy()->addHours(6),
            'reason' => 'Annual deep clean',
        ]);

        $errors = app(AmenityBookingService::class)
            ->validate($amenity, $unit, $start, $start->copy()->addHours(2));

        $this->assertStringContainsString('unavailable during that period', implode(' ', $errors));
    }

    public function test_a_closed_day_is_refused(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);

        // Next Sunday, with the amenity closed on Sundays (ISO day 7).
        $sunday = Carbon::now()->next(Carbon::SUNDAY)->setTime(10, 0);
        $amenity = $this->amenity(['available_days' => [1, 2, 3, 4, 5, 6]]);

        $errors = app(AmenityBookingService::class)
            ->validate($amenity, $unit, $sunday, $sunday->copy()->addHours(2));

        $this->assertStringContainsString('closed on the selected day', implode(' ', $errors));
    }

    public function test_hourly_charging_multiplies_by_the_hours_booked(): void
    {
        $this->makeSociety();
        $amenity = $this->amenity(['charge_amount' => 250, 'charge_basis' => 'per_hour']);

        $start = now()->addDays(2)->setTime(9, 0);

        $this->assertEqualsWithDelta(750.0, $amenity->chargeFor($start, $start->copy()->addHours(3)), 0.01);
    }

    public function test_an_amenity_needing_approval_starts_as_pending(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society);
        $user = $this->makeUser($society);
        $amenity = $this->amenity(['requires_approval' => true]);

        $start = now()->addDays(3)->setTime(10, 0);
        $booking = app(AmenityBookingService::class)->book($amenity, $unit, $start, $start->copy()->addHours(2), $user);

        $this->assertSame('pending', $booking->status);
    }
}
