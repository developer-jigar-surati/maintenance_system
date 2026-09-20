<?php

namespace App\Livewire\Governance;

use App\Enums\Permission;
use App\Models\Poll;
use App\Models\PollOption;
use App\Services\Governance\VotingService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class PollShow extends Component
{
    public Poll $poll;

    public ?int $choice = null;

    public function mount(Poll $poll): void
    {
        $this->poll = $poll->load(['options', 'votes', 'meeting', 'resolution']);
    }

    public function vote(VotingService $voting): void
    {
        $this->validate(['choice' => 'required|exists:poll_options,id']);

        try {
            $voting->cast($this->poll, auth()->user(), PollOption::findOrFail($this->choice));
        } catch (\DomainException $e) {
            $this->dispatch('notify', message: $e->getMessage(), tone: 'critical');

            return;
        }

        $this->poll->refresh()->load('options', 'votes');
        $this->dispatch('notify', message: 'Your vote has been recorded.', tone: 'positive');
    }

    public function close(VotingService $voting): void
    {
        Gate::authorize(Permission::POLL_MANAGE);

        $voting->close($this->poll);
        $this->poll->refresh()->load('options', 'votes', 'resolution');

        $this->dispatch('notify', message: 'Poll closed and the result recorded.', tone: 'positive');
    }

    public function render()
    {
        $voting = app(VotingService::class);

        return view('livewire.governance.poll-show', [
            'hasVoted' => $this->poll->hasVoted(auth()->user()),
            'tally' => $this->poll->resultsVisible() ? $this->poll->tally() : null,
            'turnout' => $voting->turnout($this->poll),
            'canManage' => auth()->user()->can(Permission::POLL_MANAGE),
        ])->title($this->poll->title);
    }
}
