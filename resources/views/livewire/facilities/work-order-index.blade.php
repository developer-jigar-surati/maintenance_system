<div>
    <x-ui.page-header title="Work orders" description="Jobs raised from tickets, schedules and by hand." />

    <x-ui.table
        :headers="['Work order', 'Source', 'Asset', 'Assigned to', 'Scheduled', 'Priority', 'Status']"
        :is-empty="$orders->isEmpty()"
        empty="No work orders match these filters"
        empty-icon="wrench"
        caption="Work orders with source, assignee, priority and status"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Number or title" aria-label="Search work orders" />
            </div>

            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-32">
                <option value="">All statuses</option>
                    <option value="open">Open</option>
                    <option value="assigned">Assigned</option>
                    <option value="in_progress">In progress</option>
                    <option value="on_hold">On hold</option>
                    <option value="completed">Completed</option>
                    <option value="verified">Verified</option>
            </x-ui.select>

            <x-ui.select wire:model.live="priority" aria-label="Filter by priority" class="w-auto min-w-32">
                <option value="">Any priority</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
            </x-ui.select>

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($orders as $order)
            <x-ui.tr>
                <x-ui.td label="Work order" primary>
                    {{ $order->work_order_number }}
                    <span class="block text-xs text-muted">{{ $order->title }}</span>
                </x-ui.td>
                <x-ui.td label="Source">
                    {{ ucfirst($order->source) }}
                    @if ($order->complaint)<span class="block text-xs text-muted">{{ $order->complaint->ticket_number }}</span>@endif
                </x-ui.td>
                <x-ui.td label="Asset">
                    {{ $order->asset?->name ?? "-" }}
                </x-ui.td>
                <x-ui.td label="Assigned to">
                    {{ $order->assignee?->name ?? $order->vendor?->name ?? "Unassigned" }}
                </x-ui.td>
                <x-ui.td label="Scheduled">
                    @if ($order->scheduled_for){{ $order->scheduled_for->format("j M Y") }}@if ($order->isOverdue())<span class="block text-xs font-medium text-[var(--color-critical)]">Overdue</span>@endif @else<span class="text-muted">–</span>@endif
                </x-ui.td>
                <x-ui.td label="Priority">
                    <x-ui.badge :tone="match ($order->priority) { 'urgent' => 'critical', 'high' => 'caution', 'medium' => 'info', default => 'neutral' }">{{ ucfirst($order->priority) }}</x-ui.badge>
                </x-ui.td>
                <x-ui.td label="Status">
                    <x-ui.status :value="$order->status" />
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $orders->links() }}</x-slot:footer>
    </x-ui.table>
</div>
