<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    /**
     * How long the residency has run, in words.
     *
     * Carbon's own diff happily says "0 seconds" for a stay recorded and
     * ended the same day, and "8 months 17 hours" for a long one. Neither is
     * something a person would write on a form.
     */
    public function durationLabel(): string
    {
        if ($this->start_date === null) {
            return '';
        }

        $start = $this->start_date->copy()->startOfDay();
        $end = ($this->end_date ?? now())->copy()->startOfDay();

        $days = (int) $start->diffInDays($end);

        return match (true) {
            $days < 1 => 'same day',
            $days < 31 => $days.' '.($days === 1 ? 'day' : 'days'),
            $days < 365 => ($m = (int) $start->diffInMonths($end)).' '.($m === 1 ? 'month' : 'months'),
            default => $this->yearsAndMonths($start, $end),
        };
    }

    private function yearsAndMonths(Carbon $start, Carbon $end): string
    {
        $months = (int) $start->diffInMonths($end);
        $years = intdiv($months, 12);
        $rest = $months % 12;

        $label = $years.' '.($years === 1 ? 'year' : 'years');

        return $rest === 0 ? $label : $label.' '.$rest.' '.($rest === 1 ? 'month' : 'months');
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
