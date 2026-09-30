<?php

namespace App\Services\Property;

use App\Models\Unit;
use App\Models\UnitResident;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Who lives in a unit, who used to, and the moves between.
 *
 * Occupancy is the fact the rest of the system reads from: bills address the
 * billing contact, notices reach whoever lives there now, and the gate rings
 * the right flat. A move recorded loosely -- a resident edited in place, an
 * old tenant simply deleted -- silently rewrites who a past bill was for, so
 * every change here closes one row and opens another, and the unit's own
 * status is derived rather than typed.
 */
class Occupancy
{
    /**
     * Records someone moving in.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function moveIn(Unit $unit, User $user, array $attributes = [], ?User $by = null): UnitResident
    {
        $relation = $attributes['relation'] ?? 'tenant';
        $startDate = Carbon::parse($attributes['start_date'] ?? now())->startOfDay();

        return DB::transaction(function () use ($unit, $user, $attributes, $relation, $startDate, $by) {
            // A unit has one primary resident and one billing contact. Taking
            // either is taking it from whoever held it.
            $taking = array_values(array_filter([
                ($attributes['is_primary'] ?? false) ? 'is_primary' : null,
                ($attributes['is_billing_contact'] ?? false) ? 'is_billing_contact' : null,
            ]));

            if ($taking !== []) {
                foreach ($this->currentFor($unit) as $existing) {
                    $existing->forceFill(array_fill_keys($taking, false))->save();
                }
            }

            $resident = UnitResident::create([
                'society_id' => $unit->society_id,
                'unit_id' => $unit->id,
                'user_id' => $user->id,
                'relation' => $relation,
                'is_primary' => (bool) ($attributes['is_primary'] ?? false),
                'is_billing_contact' => (bool) ($attributes['is_billing_contact'] ?? false),
                'start_date' => $startDate,
                'agreement_start_date' => $attributes['agreement_start_date'] ?? null,
                'agreement_end_date' => $attributes['agreement_end_date'] ?? null,
                'rent_amount' => $attributes['rent_amount'] ?? null,
                'deposit_amount' => $attributes['deposit_amount'] ?? null,
                'police_verification_done' => (bool) ($attributes['police_verification_done'] ?? false),
                'status' => 'active',
                'recorded_by' => $by?->id,
            ]);

            $this->refreshStatus($unit);

            return $resident;
        });
    }

    /**
     * Records someone moving out.
     *
     * The row is closed, never deleted: a receipt from three years ago has to
     * keep naming the person who actually paid it.
     */
    public function moveOut(
        UnitResident $resident,
        ?Carbon $on = null,
        ?string $reason = null,
        ?string $notes = null,
        ?User $by = null,
    ): UnitResident {
        $on = ($on ?? now())->copy()->startOfDay();

        return DB::transaction(function () use ($resident, $on, $reason, $notes, $by) {
            $resident->forceFill([
                'status' => 'ended',
                'end_date' => $on,
                'move_out_reason' => $reason,
                'handover_notes' => $notes,
                'is_primary' => false,
                'is_billing_contact' => false,
                'recorded_by' => $by?->id ?? $resident->recorded_by,
            ])->save();

            $unit = $resident->relationLoaded('unit') && $resident->unit
                ? $resident->unit
                : Unit::findOrFail($resident->unit_id);

            // Bills must still reach someone. When the last billing contact
            // leaves, it falls back to whoever remains, owners first.
            $this->ensureBillingContact($unit);
            $this->refreshStatus($unit);

            return $resident->fresh();
        });
    }

    /**
     * Records a sale: the outgoing owner's residency is closed and the new
     * owner's opened on the same date, so the unit is never ownerless and
     * never doubly owned.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transferOwnership(
        Unit $unit,
        User $newOwner,
        ?Carbon $on = null,
        array $attributes = [],
        ?User $by = null,
    ): UnitResident {
        $on = ($on ?? now())->copy()->startOfDay();

        return DB::transaction(function () use ($unit, $newOwner, $on, $attributes, $by) {
            $this->currentFor($unit)
                ->filter(fn (UnitResident $r) => $r->isOwner())
                ->each(fn (UnitResident $r) => $this->moveOut(
                    $r, $on, $attributes['reason'] ?? 'Unit sold', $attributes['notes'] ?? null, $by,
                ));

            return $this->moveIn($unit, $newOwner, [
                'relation' => 'owner',
                'start_date' => $on,
                'is_primary' => true,
                'is_billing_contact' => true,
            ] + $attributes, $by);
        });
    }

    /**
     * Every residency a unit has had, newest first.
     *
     * @return Collection<int, UnitResident>
     */
    public function historyFor(Unit $unit): Collection
    {
        return UnitResident::query()
            ->where('unit_id', $unit->id)
            ->with('user')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Every unit a person has lived in, newest first.
     *
     * @return Collection<int, UnitResident>
     */
    public function historyForUser(User $user): Collection
    {
        return UnitResident::query()
            ->where('user_id', $user->id)
            ->with(['unit.block'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The unit's occupancy status, worked out from who lives there.
     *
     * Typed by hand this drifts: a tenant moves out and the unit reads
     * "rented" for a year. Derived, it cannot.
     */
    public function refreshStatus(Unit $unit): string
    {
        // A committee sets these deliberately; an empty flat under
        // construction is not the same as a vacant one.
        if (in_array($unit->occupancy_status, ['under_construction', 'locked'], true)) {
            return $unit->occupancy_status;
        }

        $current = $this->currentFor($unit);

        $status = match (true) {
            $current->isEmpty() => 'vacant',
            $current->contains(fn (UnitResident $r) => $r->isTenant()) => 'rented',
            default => 'owner_occupied',
        };

        if ($unit->occupancy_status !== $status) {
            $unit->forceFill(['occupancy_status' => $status])->save();
        }

        return $status;
    }

    /** @return Collection<int, UnitResident> */
    public function currentFor(Unit $unit): Collection
    {
        return UnitResident::query()
            ->where('unit_id', $unit->id)
            ->active()
            ->with('user')
            ->get();
    }

    /**
     * Makes sure a unit still has somewhere to send its bill.
     *
     * Owners are preferred over tenants: an unpaid bill is ultimately the
     * owner's liability whoever is living there.
     */
    private function ensureBillingContact(Unit $unit): void
    {
        $current = $this->currentFor($unit);

        if ($current->isEmpty() || $current->contains(fn (UnitResident $r) => $r->is_billing_contact)) {
            return;
        }

        $successor = $current->first(fn (UnitResident $r) => $r->isOwner()) ?? $current->first();

        $successor->forceFill(['is_billing_contact' => true])->save();
    }
}
