<div>
    <x-ui.page-header title="Staff" description="Everyone working for the society, employed or outsourced." />

    <x-ui.table
        :headers="['Name', 'Department', 'Designation', 'Engaged via', 'Phone', 'Verified', 'Status']"
        :is-empty="$staff->isEmpty()"
        empty="No staff recorded yet"
        empty-icon="identification"
        caption="Staff with department, designation and employment type"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Name, code or phone" aria-label="Search staff" />
            </div>

            <x-ui.select wire:model.live="department" aria-label="Filter by department" class="w-auto min-w-32">
                <option value="">All departments</option>
                    <option value="security">Security</option>
                    <option value="housekeeping">Housekeeping</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="administration">Administration</option>
                    <option value="accounts">Accounts</option>
                    <option value="gardening">Gardening</option>
                    <option value="other">Other</option>
            </x-ui.select>

            <x-ui.select wire:model.live="status" aria-label="Filter by status" class="w-auto min-w-32">
                <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="on_leave">On leave</option>
                    <option value="inactive">Inactive</option>
                    <option value="terminated">Terminated</option>
            </x-ui.select>

            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($staff as $member)
            <x-ui.tr>
                <x-ui.td label="Name" primary>
                    {{ $member->name }}
                    @if ($member->employee_code)<span class="block text-xs text-muted">{{ $member->employee_code }}</span>@endif
                </x-ui.td>
                <x-ui.td label="Department">
                    {{ $member->departmentLabel() }}
                </x-ui.td>
                <x-ui.td label="Designation">
                    {{ $member->designation ?? "-" }}
                </x-ui.td>
                <x-ui.td label="Engaged via">
                    {{ $member->vendor?->name ?? ucwords(str_replace("_", " ", $member->employment_type)) }}
                </x-ui.td>
                <x-ui.td label="Phone">
                    <span class="numeric">{{ $member->phone ?? "-" }}</span>
                </x-ui.td>
                <x-ui.td label="Verified">
                    @if ($member->police_verified)<x-ui.badge tone="positive" dot>Police verified</x-ui.badge>@else<x-ui.badge tone="caution" dot>Pending</x-ui.badge>@endif
                </x-ui.td>
                <x-ui.td label="Status">
                    <x-ui.status :value="$member->status" />
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $staff->links() }}</x-slot:footer>
    </x-ui.table>
</div>
