<?php

namespace App\Livewire\Governance;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Poll;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class PollIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    protected function sortableColumns(): array
    {
        return ['title', 'starts_at', 'ends_at', 'status'];
    }

    protected function defaultSort(): array
    {
        return ['ends_at', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    public function render()
    {
        $query = Poll::query()
            ->withCount('votes')
            ->with('options')
            ->when($this->search !== '', fn (Builder $q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status));

        return view('livewire.governance.poll-index', [
            'polls' => $this->applySort($query)->paginate($this->perPage),
        ])->title('Polls');
    }
}
