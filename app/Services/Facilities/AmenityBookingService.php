<?php

namespace App\Services\Facilities;

use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\Unit;
use App\Models\User;
use App\Services\NumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Books shared facilities, enforcing the rules a committee actually cares
 * about: no double bookings, no reserving the hall a year out, and a cap on
 * how much one unit can hold at once.
 */
class AmenityBookingService
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * Validates a proposed booking and returns the reasons it cannot be made.
     * Returning all failures at once means the resident fixes everything in
     * one go instead of discovering problems one at a time.
     *
     * @return array<int, string> empty when the slot is bookable
     */
    public function validate(Amenity $amenity, Unit $unit, Carbon $start, Carbon $end, int $guests = 0, ?AmenityBooking $ignore = null): array
    {
        $errors = [];

        if (! $amenity->is_active || ! $amenity->is_bookable) {
            $errors[] = 'This amenity is not available for booking.';

            return $errors;
        }

        if ($end->lte($start)) {
            $errors[] = 'The end time must be after the start time.';

            return $errors;
        }

        if ($start->isPast()) {
            $errors[] = 'Bookings cannot be made for a time in the past.';
        }

        $minutes = $start->diffInMinutes($end);

        if ($minutes < $amenity->min_booking_minutes) {
            $errors[] = "The minimum booking is {$amenity->min_booking_minutes} minutes.";
        }

        if ($minutes > $amenity->max_booking_minutes) {
            $errors[] = "The maximum booking is {$amenity->max_booking_minutes} minutes.";
        }

        if ($start->lt(now()->addHours($amenity->min_notice_hours))) {
            $errors[] = "Bookings need at least {$amenity->min_notice_hours} hours notice.";
        }

        if ($start->gt(now()->addDays($amenity->advance_booking_days))) {
            $errors[] = "Bookings can only be made up to {$amenity->advance_booking_days} days ahead.";
        }

        if (! $amenity->isOpenOn($start)) {
            $errors[] = 'The amenity is closed on the selected day.';
        }

        if ($amenity->opens_at && $start->format('H:i:s') < $amenity->opens_at) {
            $errors[] = 'The selected start time is before opening hours.';
        }

        if ($amenity->closes_at && $end->format('H:i:s') > $amenity->closes_at) {
            $errors[] = 'The selected end time is after closing hours.';
        }

        if ($amenity->capacity && $guests > $amenity->capacity) {
            $errors[] = "Capacity is {$amenity->capacity} people.";
        }

        if ($this->hasClash($amenity, $start, $end, $ignore)) {
            $errors[] = 'That slot is already booked.';
        }

        if ($this->isBlackedOut($amenity, $start, $end)) {
            $errors[] = 'The amenity is unavailable during that period.';
        }

        if ($this->activeBookingCount($amenity, $unit, $ignore) >= $amenity->max_active_bookings_per_unit) {
            $errors[] = "Your unit already has {$amenity->max_active_bookings_per_unit} active bookings for this amenity.";
        }

        return $errors;
    }

    /**
     * Creates a booking, re-checking for clashes inside a transaction so two
     * residents submitting at the same moment cannot both win the slot.
     *
     * @throws \DomainException when the booking is not permitted
     */
    public function book(Amenity $amenity, Unit $unit, Carbon $start, Carbon $end, User $by, array $attributes = []): AmenityBooking
    {
        $guests = (int) ($attributes['guests_count'] ?? 0);

        return DB::transaction(function () use ($amenity, $unit, $start, $end, $by, $attributes, $guests) {
            $errors = $this->validate($amenity, $unit, $start, $end, $guests);

            if ($errors !== []) {
                throw new \DomainException(implode(' ', $errors));
            }

            // Re-check under a lock; validate() above raced with other requests.
            if ($this->hasClash($amenity, $start, $end, null, lock: true)) {
                throw new \DomainException('That slot was just taken by another booking.');
            }

            return AmenityBooking::create([
                'society_id' => $amenity->society_id,
                'amenity_id' => $amenity->id,
                'unit_id' => $unit->id,
                'booked_by' => $by->id,
                'booking_number' => $this->numbers->next(NumberGenerator::BOOKING, $amenity->society),
                'starts_at' => $start,
                'ends_at' => $end,
                'guests_count' => $guests,
                'purpose' => $attributes['purpose'] ?? null,
                'charge_amount' => $amenity->chargeFor($start, $end, $guests),
                'deposit_amount' => $amenity->deposit_amount,
                'status' => $amenity->requires_approval ? 'pending' : 'approved',
                'approved_at' => $amenity->requires_approval ? null : now(),
                'notes' => $attributes['notes'] ?? null,
            ]);
        });
    }

    public function approve(AmenityBooking $booking, User $approver): AmenityBooking
    {
        $booking->forceFill([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();

        return $booking;
    }

    public function reject(AmenityBooking $booking, User $approver, string $reason): AmenityBooking
    {
        $booking->forceFill([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        return $booking;
    }

    public function cancel(AmenityBooking $booking, ?string $reason = null): AmenityBooking
    {
        if (! $booking->isCancellable()) {
            throw new \DomainException('This booking can no longer be cancelled.');
        }

        $booking->forceFill([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ])->save();

        return $booking;
    }

    /**
     * Slots already taken on a given day, used to grey out the picker rather
     * than letting residents guess.
     *
     * @return Collection<int, AmenityBooking>
     */
    public function bookingsOn(Amenity $amenity, Carbon $day): Collection
    {
        return $amenity->blockingBookings()
            ->whereDate('starts_at', $day->toDateString())
            ->orderBy('starts_at')
            ->get();
    }

    private function hasClash(Amenity $amenity, Carbon $start, Carbon $end, ?AmenityBooking $ignore = null, bool $lock = false): bool
    {
        return $amenity->blockingBookings()
            ->overlapping($start, $end)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->exists();
    }

    private function isBlackedOut(Amenity $amenity, Carbon $start, Carbon $end): bool
    {
        return $amenity->blackouts()
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();
    }

    private function activeBookingCount(Amenity $amenity, Unit $unit, ?AmenityBooking $ignore = null): int
    {
        return $amenity->bookings()
            ->where('unit_id', $unit->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('ends_at', '>=', now())
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->count();
    }
}
