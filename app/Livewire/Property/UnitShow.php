<?php

namespace App\Livewire\Property;

use App\Models\Unit;
use App\Services\Payments\PaymentRecorder;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A unit's full picture: who lives there, what it owes, and its history.
 */
#[Layout('components.layouts.app')]
class UnitShow extends Component
{
    public Unit $unit;

    public function mount(Unit $unit): void
    {
        $this->unit = $unit->load(['block', 'residents.user', 'vehicles', 'parkingSlots']);
    }

    public function render()
    {
        return view('livewire.property.unit-show', [
            'invoices' => $this->unit->invoices()->latest('issue_date')->take(12)->get(),
            'payments' => $this->unit->payments()->completed()->latest('paid_at')->take(8)->get(),
            'outstanding' => $this->unit->outstandingBalance(),
            'credit' => app(PaymentRecorder::class)->creditBalanceFor($this->unit),
            'openComplaints' => $this->unit->complaints()->open()->count(),
            'society' => $this->unit->society,
        ])->title($this->unit->label);
    }
}
