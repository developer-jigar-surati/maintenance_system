<?php

namespace App\Livewire\Security;

use App\Models\Unit;
use App\Models\VisitorLog;
use App\Services\Security\GateService;
use App\Support\SocietyContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The guard's screen.
 *
 * Built for one-handed use on a phone at the gate: a code lookup, a short
 * walk-in form, and a live list of who is inside.
 */
#[Layout('components.layouts.app')]
class GateConsole extends Component
{
    public string $passCode = '';

    public ?int $foundLogId = null;

    /** Walk-in form. */
    public string $visitorName = '';

    public string $phone = '';

    public string $purpose = 'guest';

    public ?int $unitId = null;

    public string $vehicleNumber = '';

    public int $accompanying = 0;

    public function lookup(GateService $gate): void
    {
        $this->validate(['passCode' => 'required|string|min:4|max:12']);

        $log = $gate->findByPassCode(app(SocietyContext::class)->check(), $this->passCode);

        if ($log === null) {
            $this->foundLogId = null;
            $this->dispatch('notify', message: 'No expected visitor matches that code.', tone: 'critical');

            return;
        }

        $this->foundLogId = $log->id;
    }

    public function checkIn(int $logId, GateService $gate): void
    {
        try {
            $gate->checkIn(VisitorLog::findOrFail($logId), auth()->user());
        } catch (\DomainException $e) {
            $this->dispatch('notify', message: $e->getMessage(), tone: 'critical');

            return;
        }

        $this->reset(['passCode', 'foundLogId']);
        $this->dispatch('notify', message: 'Visitor checked in.', tone: 'positive');
    }

    public function checkOut(int $logId, GateService $gate): void
    {
        $gate->checkOut(VisitorLog::findOrFail($logId), auth()->user());
        $this->dispatch('notify', message: 'Visitor checked out.', tone: 'positive');
    }

    public function logWalkIn(GateService $gate): void
    {
        $validated = $this->validate([
            'visitorName' => 'required|string|min:2|max:120',
            'phone' => 'nullable|string|max:20',
            'purpose' => 'required|in:guest,delivery,cab,service,vendor,staff,courier,interview,other',
            'unitId' => 'nullable|exists:units,id',
            'vehicleNumber' => 'nullable|string|max:20',
            'accompanying' => 'integer|min:0|max:50',
        ]);

        $log = $gate->logArrival(app(SocietyContext::class)->check(), [
            'visitor_name' => $validated['visitorName'],
            'phone' => $validated['phone'] ?: null,
            'purpose' => $validated['purpose'],
            'unit_id' => $validated['unitId'],
            'vehicle_number' => $validated['vehicleNumber'] ?: null,
            'accompanying_count' => $validated['accompanying'],
        ], auth()->user());

        $this->reset(['visitorName', 'phone', 'vehicleNumber', 'accompanying', 'unitId']);
        $this->dispatch('close-modal', 'walk-in');
        $this->dispatch('notify',
            message: $log->status === 'approved'
                ? 'Logged. The visitor may enter.'
                : 'Logged and sent to the resident for approval.',
            tone: 'positive');
    }

    public function render()
    {
        $society = app(SocietyContext::class)->check();

        return view('livewire.security.gate-console', [
            'found' => $this->foundLogId ? VisitorLog::with('unit.block')->find($this->foundLogId) : null,
            'inside' => app(GateService::class)->currentlyInside($society),
            'awaitingApproval' => VisitorLog::query()
                ->where('status', 'pending_approval')
                ->with('unit.block')
                ->latest()
                ->get(),
            'expected' => VisitorLog::query()
                ->whereIn('status', ['expected', 'approved'])
                ->whereDate('expected_at', '<=', now()->addDay())
                ->with('unit.block')
                ->orderBy('expected_at')
                ->take(20)
                ->get(),
            'units' => Unit::with('block')->orderBy('unit_number')->get(),
        ])->title('Gate');
    }
}
