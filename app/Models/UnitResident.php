<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a person to a unit for a period, as owner, tenant or family member.
 * Ending a tenancy closes the row rather than deleting it, so the unit keeps
 * a readable occupancy history.
 */
class UnitResident extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'agreement_start_date' => 'date',
            'agreement_end_date' => 'date',
            'rent_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'is_primary' => 'boolean',
            'is_billing_contact' => 'boolean',
            'police_verification_done' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return in_array($this->relation, ['owner', 'co_owner'], true);
    }

    public function isTenant(): bool
    {
        return $this->relation === 'tenant';
    }

    /** A tenancy whose agreement runs out within the given window. */
    public function agreementExpiringWithin(int $days): bool
    {
        return $this->agreement_end_date !== null
            && $this->agreement_end_date->isBetween(now(), now()->addDays($days));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOwners(Builder $query): Builder
    {
        return $query->whereIn('relation', ['owner', 'co_owner']);
    }

    public function scopeTenants(Builder $query): Builder
    {
        return $query->where('relation', 'tenant');
    }
}
