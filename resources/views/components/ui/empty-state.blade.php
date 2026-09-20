@props(['icon' => 'folder', 'title' => 'Nothing here yet', 'description' => null])

<div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    <span class="mb-4 flex size-12 items-center justify-center rounded-2xl surface-inset text-muted">
        <x-ui.icon :name="$icon" class="size-6" />
    </span>
    <p class="text-sm font-semibold">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-secondary">{{ $description }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
