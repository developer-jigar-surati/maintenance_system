<div>
    <x-ui.page-header title="Helpdesk" description="Raise an issue and follow it through to resolution.">
        <x-slot:actions>
            @can(\App\Enums\Permission::COMPLAINT_CREATE)
                <x-ui.button x-on:click="$dispatch('open-modal', 'raise-ticket')" icon="plus">Raise a ticket</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($stats)
        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-ui.stat label="Open" :value="$stats['open']" icon="lifebuoy" tone="info" />
            <x-ui.stat label="Past SLA" :value="$stats['breached']" icon="alert"
                :tone="$stats['breached'] > 0 ? 'critical' : 'positive'" />
            <x-ui.stat label="Resolved this month" :value="$stats['resolved_this_month']" icon="check" tone="positive" />
            <x-ui.stat label="Avg. resolution"
                :value="$stats['average_resolution_hours'] ? $stats['average_resolution_hours'].' hrs' : '—'"
                icon="clock" />
        </div>
    @endif

    <x-ui.table
        :headers="['Ticket', 'Category', 'Unit', 'Raised', 'Assigned to', 'Priority', 'Status', '']"
        :is-empty="$complaints->isEmpty()"
        empty="No tickets match these filters"
        empty-icon="lifebuoy"
        caption="Helpdesk tickets with category, priority, assignee and status"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Ticket number or title" aria-label="Search tickets" />
            </div>

            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-32">
                <option value="">All statuses</option>
                <option value="open">Open</option>
                <option value="breached">Past SLA</option>
                <option value="in_progress">In progress</option>
                <option value="on_hold">On hold</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
            </x-ui.select>

            <x-ui.select wire:model.live="priority" aria-label="Filter by priority" class="w-auto min-w-28">
                <option value="">Any priority</option>
                @foreach (['urgent' => 'Urgent', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select wire:model.live="categoryId" aria-label="Filter by category" class="w-auto min-w-32">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </x-ui.select>

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($complaints as $complaint)
            <x-ui.tr :href="route('complaints.show', $complaint)">
                <x-ui.td label="Ticket" primary>
                    <a href="{{ route('complaints.show', $complaint) }}" class="hover:underline">{{ $complaint->title }}</a>
                    <span class="block text-xs text-muted">{{ $complaint->ticket_number }}</span>
                </x-ui.td>
                <x-ui.td label="Category">{{ $complaint->category?->name ?? '—' }}</x-ui.td>
                <x-ui.td label="Unit">{{ $complaint->unit?->label ?? 'Common area' }}</x-ui.td>
                <x-ui.td label="Raised">
                    {{ $complaint->created_at->diffForHumans(short: true) }}
                    <span class="block text-xs text-muted">{{ $complaint->raisedBy?->name }}</span>
                </x-ui.td>
                <x-ui.td label="Assigned to">{{ $complaint->assignee?->name ?? 'Unassigned' }}</x-ui.td>
                <x-ui.td label="Priority">
                    <x-ui.badge :tone="match ($complaint->priority) {
                        'urgent' => 'critical', 'high' => 'caution', 'medium' => 'info', default => 'neutral',
                    }">{{ ucfirst($complaint->priority) }}</x-ui.badge>
                </x-ui.td>
                <x-ui.td label="Status">
                    <x-ui.status :value="$complaint->status" />
                    @if ($complaint->hasBreachedSla())
                        <span class="mt-1 block text-xs font-semibold text-[var(--color-critical)]">Past SLA</span>
                    @endif
                </x-ui.td>
                <x-ui.td align="right">
                    <a href="{{ route('complaints.show', $complaint) }}"
                       class="inline-flex items-center gap-1 text-xs font-semibold accent-text hover:underline">
                        Open <x-ui.icon name="chevron-right" class="size-3.5" />
                    </a>
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $complaints->links() }}</x-slot:footer>
    </x-ui.table>

    @can(\App\Enums\Permission::COMPLAINT_CREATE)
        <x-ui.modal name="raise-ticket" title="Raise a ticket">
            <form wire:submit="raise" class="space-y-4" id="raise-ticket-form">
                <x-ui.input wire:model="title" name="title" label="What is the problem?"
                    placeholder="e.g. Lift in B wing is stuck" required />

                <x-ui.select wire:model="newCategoryId" name="newCategoryId" label="Category"
                    placeholder="Choose a category" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </x-ui.select>

                @if ($myUnits->count() > 1)
                    <x-ui.select wire:model="unitId" name="unitId" label="Unit" placeholder="Which unit?">
                        @foreach ($myUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->label }}</option>
                        @endforeach
                    </x-ui.select>
                @endif

                <x-ui.input wire:model="location" name="location" label="Where exactly?"
                    hint="Optional — helps whoever attends find it." />

                <x-ui.textarea wire:model="description" name="description" label="Describe the issue" rows="4" required />

                <x-ui.select wire:model="newPriority" name="newPriority" label="Priority"
                    hint="Leave blank to use the category's default.">
                    <option value="">Category default</option>
                    @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $v => $l)
                        <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </x-ui.select>
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'raise-ticket')">Cancel</x-ui.button>
                <x-ui.button type="submit" form="raise-ticket-form">
                    <span wire:loading.remove wire:target="raise">Raise ticket</span>
                    <span wire:loading wire:target="raise">Raising&hellip;</span>
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
