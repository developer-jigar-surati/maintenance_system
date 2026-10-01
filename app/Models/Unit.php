<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * A billable property: flat, villa, plot, shop or office.
 *
 * Everything financial in the system attaches to a unit rather than to a
 * person, because owners and tenants change while the obligation does not.
 */
class Unit extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'carpet_area' => 'decimal:2',
            'built_up_area' => 'decimal:2',
            'super_built_up_area' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'opening_balance_as_on' => 'date',
            'possession_date' => 'date',
            'is_billable' => 'boolean',
            'bedrooms' => 'integer',
        ];
    }

    // Relationships -------------------------------------------------------

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(UnitResident::class);
    }

    public function activeResidents(): HasMany
    {
        return $this->residents()->where('status', 'active');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function chargeOverrides(): HasMany
    {
        return $this->hasMany(UnitChargeOverride::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function parkingSlots(): HasMany
    {
        return $this->hasMany(ParkingSlot::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function amenityBookings(): HasMany
    {
        return $this->hasMany(AmenityBooking::class);
    }

    // Derived values ------------------------------------------------------

    /** "A-101" when the unit sits in a block, otherwise just "101". */
    public function getLabelAttribute(): string
    {
        $block = $this->relationLoaded('block') ? $this->block : null;

        return $block ? "{$block->name}-{$this->unit_number}" : (string) $this->unit_number;
    }

    /**
     * Whoever currently lives here.
     *
     * `residents` and `activeResidents` are two names for overlapping rows,
     * and a caller that eager loaded one used to get a lazy-load violation
     * from a helper that happened to read the other. Whichever is loaded is
     * used; only a caller that loaded neither pays for a query.
     *
     * @return Collection<int, UnitResident>
     */
    public function currentResidents(): Collection
    {
        if ($this->relationLoaded('activeResidents')) {
            return $this->getRelation('activeResidents');
        }

        if ($this->relationLoaded('residents')) {
            return $this->getRelation('residents')->where('status', 'active')->values();
        }

        return $this->activeResidents()->with('user')->get();
    }

    public function owner(): ?UnitResident
    {
        return $this->currentResidents()
            ->firstWhere(fn (UnitResident $r) => in_array($r->relation, ['owner', 'co_owner'], true));
    }

    public function primaryResident(): ?UnitResident
    {
        $current = $this->currentResidents();

        return $current->firstWhere('is_primary', true) ?? $current->first();
    }

    public function billingContact(): ?UnitResident
    {
        return $this->currentResidents()->firstWhere('is_billing_contact', true)
            ?? $this->primaryResident();
    }

    /** Area used for per-sqft charges, falling back through the three measures. */
    public function areaFor(string $basis): float
    {
        return (float) ($this->{$basis}
            ?? $this->carpet_area
            ?? $this->built_up_area
            ?? $this->super_built_up_area
            ?? 0);
    }

    /**
     * What the unit currently owes: unpaid invoice balances plus any opening
     * balance carried in, less payments not yet applied to a bill.
     */
    public function outstandingBalance(): float
    {
        $unpaid = (float) $this->invoices()
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->sum('balance');

        $credit = (float) $this->payments()
            ->where('status', 'completed')
            ->sum('unallocated_amount');

        return round($unpaid + (float) $this->opening_balance - $credit, 2);
    }

    /** Payments received but not yet applied to any invoice. */
    public function creditBalance(): float
    {
        return round((float) $this->payments()
            ->where('status', 'completed')
            ->sum('unallocated_amount'), 2);
    }

    public function isDefaulter(): bool
    {
        return $this->invoices()
            ->where('status', 'overdue')
            ->exists();
    }

    // Scopes --------------------------------------------------------------

    public function scopeBillable(Builder $query): Builder
    {
        return $query->where('is_billable', true)->where('status', 'active');
    }

    public function scopeOccupied(Builder $query): Builder
    {
        return $query->whereIn('occupancy_status', ['owner_occupied', 'rented']);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('unit_number', 'like', "%{$term}%")
                ->orWhereHas('block', fn (Builder $b) => $b->where('name', 'like', "%{$term}%"))
                ->orWhereHas('residents.user', fn (Builder $u) => $u
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"));
        });
    }
}
