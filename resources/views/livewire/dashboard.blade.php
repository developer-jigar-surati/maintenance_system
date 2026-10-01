<div>
    <x-ui.page-header
        :title="'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')).', '.\Illuminate\Support\Str::before(auth()->user()->name, ' ')"
        :description="$society->name.' · '.now()->format('l, j F Y')"
    />

    @if ($isManagement)
        @include('livewire.partials.dashboard-management')
    @else
        @include('livewire.partials.dashboard-resident')
    @endif
</div>
