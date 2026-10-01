<div>
    <x-ui.page-header title="Notice board" description="Announcements from the committee.">
        <x-slot:actions>
            @can(\App\Enums\Permission::NOTICE_MANAGE)
                <x-ui.button x-on:click="$dispatch('open-modal', 'new-notice')" icon="plus">Post a notice</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap gap-3">
        <div class="w-full sm:max-w-xs">
            <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                placeholder="Search notices" aria-label="Search notices" />
        </div>
        <x-ui.select wire:model.live="category" aria-label="Filter by category" class="w-auto min-w-32">
            <option value="">All categories</option>
            @foreach (['general', 'urgent', 'maintenance', 'event', 'financial', 'meeting', 'security', 'regulatory', 'celebration'] as $c)
                <option value="{{ $c }}">{{ ucfirst($c) }}</option>
            @endforeach
        </x-ui.select>
        @if ($this->hasActiveFilters())
            <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
        @endif
    </div>

    @if ($notices->isEmpty())
        <x-ui.card><x-ui.empty-state icon="megaphone" title="No notices to show" /></x-ui.card>
    @else
        <div class="space-y-4">
            @foreach ($notices as $notice)
                <article class="surface-card p-5 {{ $notice->is_pinned ? 'border-l-4 border-l-[var(--accent)]' : '' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($notice->is_pinned)
                                    <x-ui.badge tone="accent">Pinned</x-ui.badge>
                                @endif
                                <x-ui.badge tone="neutral">{{ $notice->categoryLabel() }}</x-ui.badge>
                                @if ($notice->priority !== 'normal')
                                    <x-ui.badge :tone="$notice->priority === 'critical' ? 'critical' : 'caution'" dot>
                                        {{ ucfirst($notice->priority) }}
                                    </x-ui.badge>
                                @endif
                                @if ($notice->status !== 'published')
                                    <x-ui.status :value="$notice->status" />
                                @endif
                            </div>
                            <h2 class="mt-2 text-base font-semibold">{{ $notice->title }}</h2>
                        </div>

                        @if ($canManage)
                            <span class="shrink-0 text-xs text-muted">
                                {{ $notice->reads_count }} {{ \Illuminate\Support\Str::plural('read', $notice->reads_count) }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-2 selectable whitespace-pre-line text-sm leading-relaxed text-secondary">{{ $notice->body }}</div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-subtle pt-3">
                        <p class="text-xs text-muted">
                            {{ $notice->createdBy?->name }} ·
                            {{ $notice->published_at?->format('j M Y, g:i A') ?? 'Not published' }}
                        </p>
                        @unless ($notice->isReadBy(auth()->user()))
                            <button type="button" wire:click="markRead({{ $notice->id }})"
                                class="text-xs font-semibold accent-text hover:underline">
                                Mark as read
                            </button>
                        @else
                            <span class="text-xs text-muted">Read</span>
                        @endunless
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-5">{{ $notices->links() }}</div>
    @endif

    @can(\App\Enums\Permission::NOTICE_MANAGE)
        <x-ui.modal name="new-notice" title="Post a notice" max-width="xl">
            <form data-validate wire:submit="publish" class="space-y-4" id="new-notice-form">
                <x-ui.input wire:model="title" name="title" label="Title" required minlength="4" maxlength="180" />
                <x-ui.textarea wire:model="body" name="body" label="Message" rows="6" required minlength="10" />

                <div class="grid gap-3 sm:grid-cols-3">
                    <x-ui.select wire:model="newCategory" name="newCategory" label="Category" required>
                        @foreach (['general', 'urgent', 'maintenance', 'event', 'financial', 'meeting', 'security'] as $c)
                            <option value="{{ $c }}">{{ ucfirst($c) }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select wire:model="priority" name="priority" label="Priority" required>
                        <option value="normal">Normal</option>
                        <option value="important">Important</option>
                        <option value="critical">Critical</option>
                    </x-ui.select>

                    <x-ui.select wire:model="audience" name="audience" label="Who sees it" required>
                        <option value="all">Everyone</option>
                        <option value="owners">Owners only</option>
                        <option value="tenants">Tenants only</option>
                        <option value="committee">Committee only</option>
                        <option value="staff">Staff only</option>
                    </x-ui.select>
                </div>

                <label class="flex items-center gap-2.5 text-sm">
                    <input type="checkbox" wire:model="pinned" class="size-4 rounded border-strong accent-[var(--accent)]">
                    <span>Pin to the top of the board</span>
                </label>
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'new-notice')">Cancel</x-ui.button>
                <x-ui.button type="submit" form="new-notice-form">Publish</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
