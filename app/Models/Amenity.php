<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A shared facility residents can reserve: clubhouse, hall, court, guest room.
 */
class Amenity extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'charge_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'capacity' => 'integer',
            'min_booking_minutes' => 'integer',
            'max_booking_minutes' => 'integer',
            'advance_booking_days' => 'integer',
            'min_notice_hours' => 'integer',
            'max_active_bookings_per_unit' => 'integer',
            'is_bookable' => 'boolean',
            'requires_approval' => 'boolean',
            'bill_to_unit' => 'boolean',
            'is_active' => 'boolean',
            'available_days' => 'array',
            'opens_at' => 'string',
            'closes_at' => 'string',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(AmenityBooking::class);
    }

    public function blackouts(): HasMany
    {
        return $this->hasMany(AmenityBlackout::class);
    }

    /** Bookings that hold a slot: pending approval or already approved. */
    public function blockingBookings(): HasMany
    {
        return $this->bookings()->whereIn('status', ['pending', 'approved']);
    }

    public function isOpenOn(Carbon $moment): bool
    {
        $days = $this->available_days;

        return blank($days) || in_array((int) $moment->isoWeekday(), array_map('intval', $days), true);
    }

    /** What a booking of the given span costs, before any deposit. */
    public function chargeFor(Carbon $start, Carbon $end, int $guests = 0): float
    {
        $rate = (float) $this->charge_amount;

        return round(match ($this->charge_basis) {
            'free' => 0.0,
            'per_hour' => $rate * max(1, ceil($start->diffInMinutes($end) / 60)),
            'per_day' => $rate * max(1, (int) ceil($start->diffInDays($end) ?: 1)),
            'per_person' => $rate * max(1, $guests),
            default => $rate, // per_slot
        }, 2);
    }

    public function typeLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->type));
    }

    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_bookable', true);
    }
}
