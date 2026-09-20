<?php

namespace App\Livewire\Property;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Unit;
use App\Support\SocietyContext;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The resident directory.
 *
 * Contact details are a privacy matter, so phone numbers are shown to fellow
 * residents only when the society has opted in; committee members always see
 * them because they need to make the call.
 */
#[Layout('components.layouts.app')]
class DirectoryIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $blockId = '';

    protected function defaultSort(): array
    {
        return ['unit_number', 'asc'];
    }

    protected function sortableColumns(): array
    {
        return ['unit_number'];
    }

    protected function filterProperties(): array
    {
        return ['blockId'];
    }

    public function render()
    {
        $society = app(SocietyContext::class)->check();
        $user = auth()->user();

        $query = Unit::query()
            ->with(['block', 'activeResidents.user'])
            ->whereHas('activeResidents')
            ->search($this->search)
            ->when($this->blockId !== '', fn (Builder $q) => $q->where('block_id', $this->blockId));

        return view('livewire.property.directory-index', [
            'units' => $this->applySort($query)->paginate($this->perPage),
            'blocks' => \App\Models\Block::orderBy('name')->get(),
            'showContacts' => $user->hasManagementRole()
                || (bool) $society->setting('directory.show_phone_to_residents', false),
        ])->title('Directory');
    }
}
