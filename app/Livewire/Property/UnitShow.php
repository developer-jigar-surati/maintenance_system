<?php

namespace App\Livewire\Property;

use App\Enums\Permission;
use App\Models\Unit;
use App\Models\UnitResident;
use App\Models\User;
use App\Services\Payments\PaymentRecorder;
use App\Services\Property\Occupancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A unit's full picture: who lives there, what it owes, and its history.
 */
#[Layout('components.layouts.app')]
class UnitShow extends Component
{
    public Unit $unit;

    /** Whether the occupancy list shows only who lives there now. */
    public bool $showPastResidents = false;

    // --- move-in form ------------------------------------------------------

    public string $personName = '';

    public string $personEmail = '';

    public string $personPhone = '';

    public string $relation = 'tenant';

    public string $startDate = '';

    public string $agreementEnd = '';

    public ?float $rentAmount = null;

    public bool $isBillingContact = false;

    // --- move-out form -----------------------------------------------------

    public ?int $movingOutId = null;

    public string $moveOutDate = '';

    public string $moveOutReason = '';

    public string $handoverNotes = '';

    public function mount(Unit $unit): void
    {
        $this->unit = $unit->load(['block', 'residents.user', 'vehicles', 'parkingSlots']);
        $this->startDate = now()->toDateString();
        $this->moveOutDate = now()->toDateString();
    }

    /**
     * Records someone moving in.
     *
     * An existing account is reused rather than duplicated: the same person
     * often moves between flats in the same society, and their history should
     * follow them.
     */
    public function moveIn(Occupancy $occupancy): void
    {
        Gate::authorize(Permission::RESIDENT_MANAGE);

        $validated = $this->validate([
            'personName' => 'required|string|min:2|max:120',
            'personEmail' => 'required|email|max:180',
            'personPhone' => 'nullable|string|max:20',
            'relation' => 'required|in:owner,co_owner,tenant,family_member,occupant',
            'startDate' => 'required|date',
            'agreementEnd' => 'nullable|date|after:startDate',
            'rentAmount' => 'nullable|numeric|min:0|max:10000000',
        ]);

        $user = User::firstOrCreate(
            ['email' => $validated['personEmail']],
            [
                'name' => $validated['personName'],
                'phone' => $validated['personPhone'] ?: null,
                'password' => Str::random(16),
            ],
        );

        $society = $this->unit->resolveSociety();

        $society->users()->syncWithoutDetaching([
            $user->id => ['status' => 'active', 'joined_at' => now()],
        ]);

        $occupancy->moveIn($this->unit, $user, [
            'relation' => $validated['relation'],
            'start_date' => $validated['startDate'],
            'agreement_start_date' => $validated['relation'] === 'tenant' ? $validated['startDate'] : null,
            'agreement_end_date' => $validated['agreementEnd'] ?: null,
            'rent_amount' => $validated['rentAmount'],
            'is_primary' => $this->currentResidents()->isEmpty(),
            'is_billing_contact' => $this->isBillingContact || $this->currentResidents()->isEmpty(),
        ], auth()->user());

        $this->reset(['personName', 'personEmail', 'personPhone', 'agreementEnd', 'rentAmount', 'isBillingContact']);
        $this->unit->refresh()->load('residents.user');
        $this->dispatch('close-modal', 'move-in');
        $this->dispatch('notify', message: "{$user->name} added to {$this->unit->label}.", tone: 'positive');
    }

    /** The past-residents toggle, which is a second way into the same data. */
    public function updatedShowPastResidents(bool $value): void
    {
        if ($value && ! auth()->user()->can(Permission::HISTORY_VIEW)) {
            $this->showPastResidents = false;

            abort(403);
        }
    }

    public function startMoveOut(int $residentId): void
    {
        Gate::authorize(Permission::RESIDENT_MANAGE);

        $this->movingOutId = $residentId;
        $this->moveOutDate = now()->toDateString();
        $this->reset(['moveOutReason', 'handoverNotes']);

        $this->dispatch('open-modal', 'move-out');
    }

    public function moveOut(Occupancy $occupancy): void
    {
        Gate::authorize(Permission::RESIDENT_MANAGE);

        $this->validate([
            'moveOutDate' => 'required|date',
            'moveOutReason' => 'nullable|string|max:160',
            'handoverNotes' => 'nullable|string|max:2000',
        ]);

        $resident = UnitResident::where('unit_id', $this->unit->id)->findOrFail($this->movingOutId);

        $occupancy->moveOut(
            $resident,
            Carbon::parse($this->moveOutDate),
            $this->moveOutReason ?: null,
            $this->handoverNotes ?: null,
            auth()->user(),
        );

        $this->movingOutId = null;
        $this->unit->refresh()->load('residents.user');
        $this->dispatch('close-modal', 'move-out');
        $this->dispatch('notify',
            message: "{$resident->user?->name} recorded as moved out.",
            tone: 'positive');
    }

    /** Hands the billing contact to someone else who lives there now. */
    public function makeBillingContact(int $residentId): void
    {
        Gate::authorize(Permission::RESIDENT_MANAGE);

        $resident = UnitResident::where('unit_id', $this->unit->id)->active()->findOrFail($residentId);

        foreach ($this->currentResidents() as $other) {
            $other->forceFill(['is_billing_contact' => $other->is($resident)])->save();
        }

        $this->unit->refresh()->load('residents.user');
        $this->dispatch('notify',
            message: "Bills for {$this->unit->label} now go to {$resident->user?->name}.",
            tone: 'positive');
    }

    /** @return Collection<int, UnitResident> */
    private function currentResidents()
    {
        return $this->unit->residents->where('status', 'active');
    }

    public function render()
    {
        $occupancy = app(Occupancy::class);
        $canSeeHistory = auth()->user()->can(Permission::HISTORY_VIEW);

        // Filtered in the query, not the template: a past resident hidden by
        // an @if is still sent to the browser, where anyone can read it.
        $history = $canSeeHistory
            ? $occupancy->historyFor($this->unit)
            : $occupancy->currentFor($this->unit);

        return view('livewire.property.unit-show', [
            'invoices' => $this->unit->invoices()->latest('issue_date')->take(12)->get(),
            'payments' => $this->unit->payments()->completed()->latest('paid_at')->take(8)->get(),
            'outstanding' => $this->unit->outstandingBalance(),
            'credit' => app(PaymentRecorder::class)->creditBalanceFor($this->unit),
            'openComplaints' => $this->unit->complaints()->open()->count(),
            'society' => $this->unit->society,
            'history' => $history,
            'pastCount' => $canSeeHistory ? $history->where('status', 'ended')->count() : 0,
            'canSeeHistory' => $canSeeHistory,
            'canManageResidents' => auth()->user()->can(Permission::RESIDENT_MANAGE),
            'movingOut' => $this->movingOutId
                ? $history->firstWhere('id', $this->movingOutId)
                : null,
        ])->title($this->unit->label);
    }
}
