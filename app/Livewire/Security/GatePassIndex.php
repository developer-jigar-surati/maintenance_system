<?php

namespace App\Livewire\Security;

use App\Enums\Permission;
use App\Livewire\Concerns\WithDataTable;
use App\Models\GatePass;
use App\Models\Unit;
use App\Services\Security\GateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class GatePassIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    public string $type = 'material_out';

    public string $issuedTo = '';

    public string $phone = '';

    public string $itemsText = '';

    public string $validTo = '';

    public ?int $unitId = null;

    public function mount(): void
    {
        $this->validTo = now()->endOfDay()->format('Y-m-d\TH:i');
        $this->unitId = auth()->user()->units()->value('units.id');
    }

    public function issue(GateService $gate): void
    {
        $validated = $this->validate([
            'type' => 'required|in:material_in,material_out,move_in,move_out,vehicle,contractor',
            'issuedTo' => 'required|string|min:2|max:120',
            'phone' => 'nullable|string|max:20',
            'itemsText' => 'nullable|string|max:2000',
            'validTo' => 'required|date|after:now',
            'unitId' => 'required|exists:units,id',
        ]);

        // One item per line, which is how people actually write a list.
        $items = collect(preg_split('/\r?\n/', $validated['itemsText'] ?? ''))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(fn ($line) => ['name' => $line, 'quantity' => 1])
            ->values()
            ->all();

        $pass = $gate->issuePass(Unit::findOrFail($validated['unitId']), [
            'type' => $validated['type'],
            'issued_to_name' => $validated['issuedTo'],
            'phone' => $validated['phone'] ?: null,
            'items' => $items ?: null,
            'valid_from' => now(),
            'valid_to' => $validated['validTo'],
        ], auth()->user());

        $this->reset(['issuedTo', 'phone', 'itemsText']);
        $this->dispatch('close-modal', 'issue-pass');
        $this->dispatch('notify', message: "Pass {$pass->pass_number} requested.", tone: 'positive');
    }

    public function approve(int $passId, GateService $gate): void
    {
        Gate::authorize(Permission::GATE_PASS_APPROVE);

        $gate->approvePass(GatePass::findOrFail($passId), auth()->user());
        $this->dispatch('notify', message: 'Pass approved.', tone: 'positive');
    }

    protected function sortableColumns(): array
    {
        return ['pass_number', 'valid_to', 'status'];
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
        $canSeeAll = $user->isSuperAdmin() || $user->can(Permission::GATE_PASS_APPROVE);

        $query = GatePass::query()
            ->with(['unit.block', 'requestedBy'])
            ->when(! $canSeeAll, fn (Builder $q) => $q->whereIn('unit_id', $user->units()->pluck('units.id')))
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('pass_number', 'like', "%{$this->search}%")
                    ->orWhere('issued_to_name', 'like', "%{$this->search}%");
            }))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status));

        return view('livewire.security.gate-pass-index', [
            'passes' => $this->applySort($query)->paginate($this->perPage),
            'canApprove' => $user->can(Permission::GATE_PASS_APPROVE),
            'myUnits' => $user->units()->with('block')->get(),
        ])->title('Gate passes');
    }
}
