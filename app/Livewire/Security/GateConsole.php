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
 * Designed for one hand, on a phone, at a gate, often in poor light and by
 * someone who is not a confident typist. Every common entry is reachable in
 * three taps and needs no typing at all:
 *
 *     who is it  ->  which flat  ->  done
 *
 * Deliveries are the most frequent arrival by a wide margin, so the couriers
 * a society actually sees are preset buttons rather than a text field. The
 * resident's own approval, where the society requires it, happens on their
 * phone; the guard never waits on a form.
 */
#[Layout('components.layouts.app')]
class GateConsole extends Component
{
    /** Which step of the entry flow is showing: purpose, who, unit, done. */
    public string $step = 'purpose';

    public string $purpose = '';

    public string $visitorName = '';

    public string $phone = '';

    public ?int $unitId = null;

    public string $unitSearch = '';

    public string $vehicleNumber = '';

    public int $accompanying = 0;

    /** Set once an entry is logged, so the guard gets a clear confirmation. */
    public ?int $lastLoggedId = null;

    public string $passCode = '';

    public ?int $foundLogId = null;

    /**
     * The couriers and ride services a society in India actually sees. One tap
     * instead of spelling out "Amazon" on a phone keypad at the gate.
     */
    public const DELIVERY_COMPANIES = [
        'Amazon', 'Flipkart', 'Swiggy', 'Zomato', 'Blinkit',
        'Zepto', 'BigBasket', 'Meesho', 'Delhivery', 'India Post',
    ];

    public const CAB_COMPANIES = ['Uber', 'Ola', 'Rapido', 'Namma Yatri'];

    public const SERVICE_KINDS = [
        'Plumber', 'Electrician', 'Carpenter', 'AC service',
        'Pest control', 'Gas delivery', 'Milk', 'Newspaper',
    ];

    // --- the entry flow ---------------------------------------------------

    public function choosePurpose(string $purpose): void
    {
        $this->purpose = $purpose;
        $this->reset(['visitorName', 'phone', 'vehicleNumber', 'accompanying', 'unitId', 'unitSearch']);

        // A guest has no preset list, so skip straight to naming them.
        $this->step = 'who';
    }

    /** Picks a preset company or trade, which doubles as the visitor's name. */
    public function chooseWho(string $name): void
    {
        $this->visitorName = $name;
        $this->step = 'unit';
    }

    public function confirmName(): void
    {
        $this->validate(['visitorName' => 'required|string|min:2|max:120']);

        $this->step = 'unit';
    }

    public function chooseUnit(int $unitId, GateService $gate): void
    {
        $this->unitId = $unitId;
        $this->log($gate);
    }

    /** Deliveries to the gate desk, common when nobody is home. */
    public function logWithoutUnit(GateService $gate): void
    {
        $this->unitId = null;
        $this->log($gate);
    }

    private function log(GateService $gate): void
    {
        $society = app(SocietyContext::class)->check();

        $log = $gate->logArrival($society, [
            'visitor_name' => $this->visitorName ?: ucfirst($this->purpose),
            'phone' => $this->phone ?: null,
            'purpose' => $this->purpose,
            'unit_id' => $this->unitId,
            'vehicle_number' => $this->vehicleNumber ?: null,
            'accompanying_count' => $this->accompanying,
        ], auth()->user());

        // A visit with nobody to ask is allowed straight through; the society
        // setting only governs visits addressed to a unit.
        if ($this->unitId === null && $log->status === 'pending_approval') {
            $gate->approveEntry($log, auth()->user());
        }

        $this->lastLoggedId = $log->id;
        $this->step = 'done';
    }

    public function startOver(): void
    {
        $this->reset(['step', 'purpose', 'visitorName', 'phone', 'unitId', 'unitSearch',
            'vehicleNumber', 'accompanying', 'lastLoggedId']);
        $this->step = 'purpose';
    }

    public function back(): void
    {
        $this->step = match ($this->step) {
            'unit' => $this->hasPresetList() ? 'who' : 'who',
            'who' => 'purpose',
            default => 'purpose',
        };
    }

    // --- one-tap actions on the lists -------------------------------------

    public function allowIn(int $logId, GateService $gate): void
    {
        $log = VisitorLog::findOrFail($logId);

        if ($log->status === 'pending_approval') {
            $gate->approveEntry($log, auth()->user());
            $log->refresh();
        }

        try {
            $gate->checkIn($log, auth()->user());
        } catch (\DomainException $e) {
            $this->dispatch('notify', message: $e->getMessage(), tone: 'critical');

            return;
        }

        $this->dispatch('notify', message: "{$log->visitor_name} checked in.", tone: 'positive');
    }

    public function checkOut(int $logId, GateService $gate): void
    {
        $log = VisitorLog::findOrFail($logId);
        $gate->checkOut($log, auth()->user());

        $this->dispatch('notify', message: "{$log->visitor_name} checked out.", tone: 'positive');
    }

    public function deny(int $logId, GateService $gate): void
    {
        $log = VisitorLog::findOrFail($logId);
        $gate->denyEntry($log, auth()->user(), 'Turned away at the gate');

        $this->dispatch('notify', message: "{$log->visitor_name} turned away.", tone: 'positive');
    }

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

    // --- helpers -----------------------------------------------------------

    public function hasPresetList(): bool
    {
        return in_array($this->purpose, ['delivery', 'cab', 'service'], true);
    }

    /** @return array<int, string> */
    public function presetList(): array
    {
        return match ($this->purpose) {
            'delivery' => self::DELIVERY_COMPANIES,
            'cab' => self::CAB_COMPANIES,
            'service' => self::SERVICE_KINDS,
            default => [],
        };
    }

    public function purposeLabel(): string
    {
        return match ($this->purpose) {
            'delivery' => 'Delivery',
            'cab' => 'Cab',
            'guest' => 'Guest',
            'service' => 'Service visit',
            'staff' => 'Staff',
            default => ucfirst($this->purpose),
        };
    }

    public function render()
    {
        $society = app(SocietyContext::class)->check();

        // The unit picker is a search, not a dropdown of hundreds: a guard
        // types two or three characters of the flat number at most.
        $units = Unit::query()
            ->with('block')
            ->when($this->unitSearch !== '', fn ($q) => $q->search($this->unitSearch))
            ->orderBy('unit_number')
            ->limit($this->unitSearch === '' ? 60 : 40)
            ->get();

        return view('livewire.security.gate-console', [
            'units' => $units,
            'society' => $society,
            'lastLogged' => $this->lastLoggedId
                ? VisitorLog::with('unit.block')->find($this->lastLoggedId)
                : null,
            'found' => $this->foundLogId
                ? VisitorLog::with('unit.block')->find($this->foundLogId)
                : null,
            'inside' => VisitorLog::query()->inside()->with('unit.block')->orderByDesc('entered_at')->get(),
            'waiting' => VisitorLog::query()
                ->where('status', 'pending_approval')
                ->with('unit.block')
                ->latest()
                ->get(),
            'expected' => VisitorLog::query()
                ->whereIn('status', ['expected', 'approved'])
                ->where(fn ($q) => $q->whereNull('expected_until')->orWhere('expected_until', '>=', now()))
                ->with('unit.block')
                ->orderBy('expected_at')
                ->limit(25)
                ->get(),
        ])->title('Gate');
    }
}
