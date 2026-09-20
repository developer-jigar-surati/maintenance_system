<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\WithDataTable;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AuditIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $event = '';

    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    protected function sortableColumns(): array
    {
        return ['created_at', 'event'];
    }

    protected function filterProperties(): array
    {
        return ['event'];
    }

    public function render()
    {
        $query = AuditLog::query()
            ->with('user')
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('description', 'like', "%{$this->search}%")
                    ->orWhere('auditable_type', 'like', "%{$this->search}%");
            }))
            ->when($this->event !== '', fn (Builder $q) => $q->where('event', $this->event));

        return view('livewire.settings.audit-index', [
            'logs' => $this->applySort($query)->paginate($this->perPage),
        ])->title('Audit log');
    }
}
