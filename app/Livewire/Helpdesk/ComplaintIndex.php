<?php

namespace App\Livewire\Helpdesk;

use App\Enums\Permission;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Services\Helpdesk\ComplaintService;
use App\Support\SocietyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ComplaintIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $priority = '';

    #[Url(except: '')]
    public string $categoryId = '';

    /** New-ticket form. */
    public string $title = '';

    public string $description = '';

    public string $location = '';

    public string $newCategoryId = '';

    public string $newPriority = '';

    public ?int $unitId = null;

    protected function sortableColumns(): array
    {
        return ['ticket_number', 'created_at', 'status', 'resolution_due_at'];
    }

    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status', 'priority', 'categoryId'];
    }

    public function raise(ComplaintService $helpdesk): void
    {
        Gate::authorize(Permission::COMPLAINT_CREATE);

        $validated = $this->validate([
            'title' => 'required|string|min:4|max:180',
            'description' => 'required|string|min:10|max:4000',
            'location' => 'nullable|string|max:120',
            'newCategoryId' => 'required|exists:complaint_categories,id',
            'newPriority' => 'nullable|in:low,medium,high,urgent',
            'unitId' => 'nullable|exists:units,id',
        ]);

        $complaint = $helpdesk->raise(app(SocietyContext::class)->check(), [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'location' => $validated['location'] ?: null,
            'complaint_category_id' => $validated['newCategoryId'],
            'priority' => $validated['newPriority'] ?: null,
            'unit_id' => $validated['unitId'] ?? auth()->user()->units()->value('units.id'),
        ], auth()->user());

        $this->reset(['title', 'description', 'location', 'newCategoryId', 'newPriority']);
        $this->dispatch('close-modal', 'raise-ticket');
        $this->dispatch('notify', message: "Ticket {$complaint->ticket_number} raised.", tone: 'positive');
    }

    public function render()
    {
        $user = auth()->user();

        $query = Complaint::query()
            ->visibleTo($user)
            ->with(['category', 'unit.block', 'assignee', 'raisedBy'])
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('ticket_number', 'like', "%{$this->search}%")
                    ->orWhere('title', 'like', "%{$this->search}%");
            }))
            ->when($this->status === 'open', fn (Builder $q) => $q->open())
            ->when($this->status === 'breached', fn (Builder $q) => $q->breached())
            ->when(! in_array($this->status, ['', 'open', 'breached'], true),
                fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->priority !== '', fn (Builder $q) => $q->where('priority', $this->priority))
            ->when($this->categoryId !== '', fn (Builder $q) => $q->where('complaint_category_id', $this->categoryId));

        return view('livewire.helpdesk.complaint-index', [
            'complaints' => $this->applySort($query)->paginate($this->perPage),
            'categories' => ComplaintCategory::where('is_active', true)->orderBy('sort_order')->get(),
            'myUnits' => $user->units()->with('block')->get(),
            'stats' => $user->can(Permission::COMPLAINT_VIEW_ALL)
                ? app(ComplaintService::class)->statistics(app(SocietyContext::class)->check())
                : null,
        ])->title('Helpdesk');
    }
}
