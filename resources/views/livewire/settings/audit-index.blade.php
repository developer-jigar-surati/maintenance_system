<div>
    <x-ui.page-header title="Audit log" description="Who changed what, and when." />

    <x-ui.table
        :headers="['When', 'Who', 'Action', 'Record', 'IP']"
        :is-empty="$logs->isEmpty()"
        empty="Nothing recorded yet"
        empty-icon="clipboard"
        caption="Audit trail of changes"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Description or record" aria-label="Search audit log" />
            </div>
            <x-ui.select wire:model.live="event" aria-label="Filter by action" class="w-auto min-w-32">
                <option value="">All actions</option>
                @foreach (['created', 'updated', 'deleted', 'restored'] as $e)
                    <option value="{{ $e }}">{{ ucfirst($e) }}</option>
                @endforeach
            </x-ui.select>
            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($logs as $log)
            <x-ui.tr>
                <x-ui.td label="When" primary>
                    {{ $log->created_at?->format('j M Y, g:i A') }}
                    <span class="block text-xs text-muted">{{ $log->created_at?->diffForHumans() }}</span>
                </x-ui.td>
                <x-ui.td label="Who">{{ $log->user?->name ?? 'System' }}</x-ui.td>
                <x-ui.td label="Action"><x-ui.badge tone="neutral">{{ ucfirst($log->event) }}</x-ui.badge></x-ui.td>
                <x-ui.td label="Record">
                    {{ $log->description ?? class_basename($log->auditable_type ?? '') }}
                    @if ($log->auditable_id)
                        <span class="numeric block text-xs text-muted">#{{ $log->auditable_id }}</span>
                    @endif
                </x-ui.td>
                <x-ui.td label="IP"><span class="numeric text-xs">{{ $log->ip_address ?? '–' }}</span></x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $logs->links() }}</x-slot:footer>
    </x-ui.table>
</div>
