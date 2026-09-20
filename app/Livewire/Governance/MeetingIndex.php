<?php

namespace App\Livewire\Governance;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MeetingIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $status = '';

    protected function sortableColumns(): array
    {
        return ['scheduled_at', 'title', 'status'];
    }

    protected function defaultSort(): array
    {
        return ['scheduled_at', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['type', 'status'];
    }

    public function render()
    {
        $query = Meeting::query()
            ->withCount(['agendaItems', 'attendees'])
            ->when($this->search !== '', fn (Builder $q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->type !== '', fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status));

        return view('livewire.governance.meeting-index', [
            'meetings' => $this->applySort($query)->paginate($this->perPage),
            'upcoming' => Meeting::query()->upcoming()->take(1)->first(),
        ])->title('Meetings');
    }
}
