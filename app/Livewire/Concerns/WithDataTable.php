<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Shared behaviour for every list screen: search, sorting, filtering and
 * pagination, with the state mirrored into the query string so a filtered
 * view can be bookmarked or sent to another committee member.
 */
trait WithDataTable
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $sortBy = '';

    #[Url(except: 'desc')]
    public string $sortDirection = 'desc';

    #[Url(except: 25)]
    public int $perPage = 25;

    /** Columns a user may sort by. Anything else is ignored, so a crafted
     *  query string cannot sort by an arbitrary column. */
    protected function sortableColumns(): array
    {
        return [];
    }

    protected function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    public function sort(string $column): void
    {
        if (! in_array($column, $this->sortableColumns(), true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    protected function applySort(Builder $query): Builder
    {
        [$column, $direction] = $this->sortBy !== '' && in_array($this->sortBy, $this->sortableColumns(), true)
            ? [$this->sortBy, $this->sortDirection === 'asc' ? 'asc' : 'desc']
            : $this->defaultSort();

        return $query->orderBy($column, $direction);
    }

    /** Any change to a filter must send the user back to page one. */
    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(array_merge(['search'], $this->filterProperties()));
        $this->resetPage();
    }

    /** Filter properties this screen adds beyond the shared search box. */
    protected function filterProperties(): array
    {
        return [];
    }

    public function hasActiveFilters(): bool
    {
        if ($this->search !== '') {
            return true;
        }

        foreach ($this->filterProperties() as $property) {
            if (filled($this->{$property})) {
                return true;
            }
        }

        return false;
    }
}
