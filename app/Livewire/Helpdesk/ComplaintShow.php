<?php

namespace App\Livewire\Helpdesk;

use App\Enums\Permission;
use App\Models\Complaint;
use App\Models\User;
use App\Services\Helpdesk\ComplaintService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ComplaintShow extends Component
{
    public Complaint $complaint;

    public string $reply = '';

    public bool $internal = false;

    public ?int $assignTo = null;

    public int $rating = 0;

    public string $feedback = '';

    public function mount(Complaint $complaint): void
    {
        // The same visibility rule as the list, applied to a direct link.
        abort_unless(
            Complaint::query()->visibleTo(auth()->user())->whereKey($complaint->id)->exists(),
            403
        );

        $this->complaint = $complaint->load([
            'category', 'unit.block', 'raisedBy', 'assignee', 'vendor',
            'comments.user', 'statusLogs.changedBy', 'workOrders',
        ]);

        $this->assignTo = $complaint->assigned_to;
    }

    public function canManage(): bool
    {
        return auth()->user()->can(Permission::COMPLAINT_MANAGE);
    }

    public function comment(ComplaintService $helpdesk): void
    {
        $this->validate(['reply' => 'required|string|min:2|max:4000']);

        // Only staff may leave a note the resident cannot see.
        $internal = $this->internal && $this->canManage();

        $helpdesk->comment($this->complaint, auth()->user(), $this->reply, $internal);

        $this->reset(['reply', 'internal']);
        $this->complaint->refresh()->load('comments.user');
        $this->dispatch('notify', message: 'Reply posted.', tone: 'positive');
    }

    public function assign(ComplaintService $helpdesk): void
    {
        Gate::authorize(Permission::COMPLAINT_MANAGE);

        $helpdesk->assign(
            $this->complaint,
            $this->assignTo ? User::find($this->assignTo) : null,
            auth()->user(),
        );

        $this->complaint->refresh()->load('assignee', 'statusLogs.changedBy');
        $this->dispatch('notify', message: 'Assignment updated.', tone: 'positive');
    }

    public function changeStatus(string $status, ComplaintService $helpdesk): void
    {
        // A resident may reopen or close their own ticket; everything else is
        // a management action.
        $residentAllowed = in_array($status, ['reopened', 'closed'], true)
            && $this->complaint->raised_by === auth()->id();

        if (! $residentAllowed) {
            Gate::authorize(Permission::COMPLAINT_MANAGE);
        }

        $helpdesk->changeStatus($this->complaint, $status, auth()->user());

        $this->complaint->refresh()->load('statusLogs.changedBy');
        $this->dispatch('notify', message: 'Ticket '.str_replace('_', ' ', $status).'.', tone: 'positive');
    }

    public function rate(ComplaintService $helpdesk): void
    {
        abort_unless($this->complaint->raised_by === auth()->id(), 403);

        $this->validate([
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $helpdesk->rate($this->complaint, $this->rating, $this->feedback ?: null);
        $this->complaint->refresh();
        $this->dispatch('notify', message: 'Thank you for the feedback.', tone: 'positive');
    }

    public function render()
    {
        return view('livewire.helpdesk.complaint-show', [
            'canManage' => $this->canManage(),
            'isOwner' => $this->complaint->raised_by === auth()->id(),
            'assignees' => $this->canManage()
                ? User::query()
                    ->whereHas('societies', fn ($q) => $q->whereKey($this->complaint->society_id))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : collect(),
        ])->title($this->complaint->ticket_number);
    }
}
