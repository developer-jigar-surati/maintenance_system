<?php

namespace App\Livewire\Security;

use App\Enums\Permission;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Unit;
use App\Models\VisitorLog;
use App\Services\Security\GateService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Visitor history, plus the resident's own pre-approval form.
 */
#[Layout('components.layouts.app')]
class VisitorIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    public string $visitorName = '';

    public string $phone = '';

    public string $purpose = 'guest';

    public string $expectedAt = '';

    public ?int $unitId = null;

    public function mount(): void
    {
        $this->expectedAt = now()->addHour()->format('Y-m-d\TH:i');
        $this->unitId = auth()->user()->units()->value('units.id');
    }

    public function preApprove(GateService $gate): void
    {
        $validated = $this->validate([
            'visitorName' => 'required|string|min:2|max:120',
            'phone' => 'nullable|string|max:20',
            'purpose' => 'required|in:guest,delivery,cab,service,vendor,staff,courier,other',
            'expectedAt' => 'required|date',
            'unitId' => 'required|exists:units,id',
        ]);

        $log = $gate->preApprove(Unit::findOrFail($validated['unitId']), [
            'visitor_name' => $validated['visitorName'],
            'phone' => $validated['phone'] ?: null,
            'purpose' => $validated['purpose'],
            'expected_at' => $validated['expectedAt'],
        ], auth()->user());

        $this->reset(['visitorName', 'phone']);
        $this->dispatch('close-modal', 'pre-approve');
        $this->dispatch('notify',
            message: "Approved. Share code {$log->pass_code} with your visitor.",
            tone: 'positive');
    }

    public function approve(int $logId, GateService $gate): void
    {
        $gate->approveEntry(VisitorLog::findOrFail($logId), auth()->user());
        $this->dispatch('notify', message: 'Entry approved.', tone: 'positive');
    }

    public function deny(int $logId, GateService $gate): void
    {
        $gate->denyEntry(VisitorLog::findOrFail($logId), auth()->user(), 'Declined by resident');
        $this->dispatch('notify', message: 'Entry declined.', tone: 'positive');
    }

    protected function sortableColumns(): array
    {
        return ['visitor_name', 'created_at', 'entered_at', 'status'];
    }

    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    public function render()
    {
        $user = auth()->user();
        $canSeeAll = $user->isSuperAdmin() || $user->can(Permission::VISITOR_VIEW);
        $unitIds = $user->units()->pluck('units.id');

        $query = VisitorLog::query()
            ->with(['unit.block', 'approvedBy'])
            // A resident sees visitors to their own unit only.
            ->when(! $canSeeAll, fn (Builder $q) => $q->whereIn('unit_id', $unitIds))
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('visitor_name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
                    ->orWhere('pass_code', 'like', "%{$this->search}%");
            }))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status));

        return view('livewire.security.visitor-index', [
            'logs' => $this->applySort($query)->paginate($this->perPage),
            'awaitingMe' => VisitorLog::query()
                ->whereIn('unit_id', $unitIds)
                ->where('status', 'pending_approval')
                ->with('unit.block')
                ->get(),
            'myUnits' => $user->units()->with('block')->get(),
        ])->title('Visitors');
    }
}
