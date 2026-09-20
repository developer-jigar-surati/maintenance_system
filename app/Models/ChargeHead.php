<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A head of account that money is billed under or spent against:
 * maintenance, sinking fund, water, parking, penalty, and so on.
 */
class ChargeHead extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_recurring' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'applies_to_unit_types' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function billingPlans(): BelongsToMany
    {
        return $this->belongsToMany(BillingPlan::class)
            ->withPivot(['rate', 'basis', 'sort_order'])
            ->withTimestamps();
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(UnitChargeOverride::class);
    }

    /**
     * Whether this head should appear on a given unit's bill, honouring both
     * the audience restriction and any unit-type filter.
     */
    public function appliesToUnit(Unit $unit): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $typesOk = $this->applies_to !== 'specific_unit_types'
            || in_array($unit->type, $this->applies_to_unit_types ?? [], true);

        if (! $typesOk) {
            return false;
        }

        return match ($this->applies_to) {
            'owners' => $unit->occupancy_status === 'owner_occupied',
            'tenants' => $unit->occupancy_status === 'rented',
            'occupied' => in_array($unit->occupancy_status, ['owner_occupied', 'rented'], true),
            default => true,
        };
    }

    /** Funds that must be reported separately from running maintenance. */
    public function isRestrictedFund(): bool
    {
        return in_array($this->fund, ['sinking', 'corpus', 'repair'], true);
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', 'expense');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
