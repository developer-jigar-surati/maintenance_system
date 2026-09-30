<?php

namespace App\Livewire\Governance;

use App\Enums\Permission;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Services\Governance\VotingService;
use Illuminate\Support\Collection;
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

    /**
     * Who voted which way, for the office bearers only.
     *
     * A secretary has to be able to settle "I never voted for that" from the
     * record, and a neighbour has no business reading it. An anonymous poll
     * stays anonymous to everyone, office bearers included -- that is what
     * the society promised when it was set up that way, and a permission
     * does not override a promise.
     *
     * @return Collection<int, PollVote>
     */
    private function voterRoll(): Collection
    {
        if ($this->poll->is_anonymous || ! auth()->user()->can(Permission::HISTORY_VIEW)) {
            return collect();
        }

        return PollVote::query()
            ->where('poll_id', $this->poll->id)
            ->with(['user', 'unit.block', 'option'])
            ->orderByDesc('voted_at')
            ->get();
    }

    public function render()
    {
        $voting = app(VotingService::class);

        return view('livewire.governance.poll-show', [
            'hasVoted' => $this->poll->hasVoted(auth()->user()),
            'tally' => $this->poll->resultsVisible() ? $this->poll->tally() : null,
            'turnout' => $voting->turnout($this->poll),
            'canManage' => auth()->user()->can(Permission::POLL_MANAGE),
            'voterRoll' => $this->voterRoll(),
            'canSeeHistory' => auth()->user()->can(Permission::HISTORY_VIEW),
        ])->title($this->poll->title);
    }
}
