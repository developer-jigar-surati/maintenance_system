<div>
    <div class="mb-5">
        <a href="{{ route('complaints.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-secondary hover:text-primary">
            <x-ui.icon name="chevron-left" class="size-4" /> All tickets
        </a>
    </div>

    <x-ui.page-header :title="$complaint->title">
        <x-slot:description>
            {{ $complaint->ticket_number }} · raised {{ $complaint->created_at->diffForHumans() }}
            by {{ $complaint->raisedBy?->name }}
        </x-slot:description>
        <x-slot:actions>
            <x-ui.status :value="$complaint->status" />
        </x-slot:actions>
    </x-ui.page-header>

    @if ($complaint->hasBreachedSla())
        <x-ui.alert tone="critical" title="Past its resolution target" class="mb-5">
            This ticket was due to be resolved {{ $complaint->resolution_due_at->diffForHumans() }}.
            @if ($complaint->escalation_level > 0)
                It has been escalated to level {{ $complaint->escalation_level }}.
            @endif
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card>
                <p class="selectable whitespace-pre-line text-sm leading-relaxed">{{ $complaint->description }}</p>
                @if ($complaint->location)
                    <p class="mt-4 text-sm text-secondary">
                        <span class="font-medium">Location:</span> {{ $complaint->location }}
                    </p>
                @endif
            </x-ui.card>

            {{-- Conversation --}}
            <x-ui.card title="Activity" padded="false">
                @php
                    $visibleComments = $complaint->comments->filter(
                        fn ($c) => ! $c->is_internal || $canManage
                    );
                @endphp

                @if ($visibleComments->isEmpty())
                    <x-ui.empty-state icon="lifebuoy" title="No replies yet"
                        description="Post an update to keep everyone informed." />
                @else
                    <ul class="divide-y divide-[var(--border-subtle)]">
                        @foreach ($visibleComments as $comment)
                            <li class="flex gap-3 px-5 py-4 {{ $comment->is_internal ? 'surface-sunken' : '' }}">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full accent-soft-bg text-xs font-bold accent-text">
                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($comment->user?->name ?? '?', 0, 1)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="flex flex-wrap items-center gap-2 text-sm">
                                        <span class="font-semibold">{{ $comment->user?->name ?? 'System' }}</span>
                                        <span class="text-xs text-muted">{{ $comment->created_at->diffForHumans() }}</span>
                                        @if ($comment->is_internal)
                                            <x-ui.badge tone="caution">Internal note</x-ui.badge>
                                        @endif
                                    </p>
                                    <p class="mt-1 selectable whitespace-pre-line text-sm text-secondary">{{ $comment->body }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="border-t border-subtle p-5">
                    <form wire:submit="comment" class="space-y-3">
                        <x-ui.textarea wire:model="reply" name="reply" label="Add a reply" rows="3"
                            placeholder="Share an update…" required />

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            @if ($canManage)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="internal" class="size-4 rounded border-strong accent-[var(--accent)]">
                                    <span>Internal note &mdash; hidden from the resident</span>
                                </label>
                            @else
                                <span></span>
                            @endif

                            <x-ui.button type="submit" size="sm">
                                <span wire:loading.remove wire:target="comment">Post reply</span>
                                <span wire:loading wire:target="comment">Posting&hellip;</span>
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </x-ui.card>

            {{-- Rating, once resolved --}}
            @if ($isOwner && $complaint->isResolved() && $complaint->rating === null)
                <x-ui.card title="How did we do?">
                    <form wire:submit="rate" class="space-y-4">
                        <div class="flex items-center gap-2" role="radiogroup" aria-label="Rating out of five">
                            @for ($i = 1; $i <= 5; $i++)
                                <button type="button" wire:click="$set('rating', {{ $i }})"
                                    class="rounded-lg border px-4 py-2 text-sm font-semibold transition-colors
                                        {{ $rating >= $i ? 'accent-bg border-transparent' : 'border-subtle text-secondary hover:surface-inset' }}"
                                    role="radio" aria-checked="{{ $rating === $i ? 'true' : 'false' }}"
                                    aria-label="{{ $i }} out of 5">
                                    {{ $i }}
                                </button>
                            @endfor
                        </div>
                        <x-ui.textarea wire:model="feedback" name="feedback" label="Anything else?" rows="2" />
                        <x-ui.button type="submit" size="sm">Submit feedback</x-ui.button>
                    </form>
                </x-ui.card>
            @elseif ($complaint->rating)
                <x-ui.card title="Resident feedback">
                    <p class="text-sm"><span class="font-semibold">{{ $complaint->rating }} / 5</span></p>
                    @if ($complaint->feedback)
                        <p class="mt-1 text-sm text-secondary">{{ $complaint->feedback }}</p>
                    @endif
                </x-ui.card>
            @endif
        </div>

        {{-- Rail --}}
        <div class="space-y-6">
            <x-ui.card title="Details">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Category</dt>
                        <dd>{{ $complaint->category?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Unit</dt>
                        <dd>{{ $complaint->unit?->label ?? 'Common area' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Priority</dt>
                        <dd>
                            <x-ui.badge :tone="match ($complaint->priority) {
                                'urgent' => 'critical', 'high' => 'caution', 'medium' => 'info', default => 'neutral',
                            }">{{ ucfirst($complaint->priority) }}</x-ui.badge>
                        </dd>
                    </div>
                    @if ($complaint->resolution_due_at)
                        <div class="flex justify-between gap-3">
                            <dt class="text-secondary">Resolve by</dt>
                            <dd class="numeric {{ $complaint->hasBreachedSla() ? 'font-semibold text-[var(--color-critical)]' : '' }}">
                                {{ $complaint->resolution_due_at->format('j M, g:i A') }}
                            </dd>
                        </div>
                    @endif
                    @if ($complaint->resolved_at)
                        <div class="flex justify-between gap-3">
                            <dt class="text-secondary">Resolved</dt>
                            <dd class="numeric">{{ $complaint->resolved_at->format('j M Y') }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            @if ($canManage)
                <x-ui.card title="Manage">
                    <div class="space-y-4">
                        <div>
                            <x-ui.select wire:model="assignTo" label="Assigned to" placeholder="Unassigned">
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}">{{ $assignee->name }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.button wire:click="assign" size="sm" variant="secondary" class="mt-2 w-full">
                                Update assignment
                            </x-ui.button>
                        </div>

                        <div class="border-t border-subtle pt-4">
                            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted">Move to</p>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach (['in_progress' => 'In progress', 'on_hold' => 'On hold', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $value => $label)
                                    @if ($complaint->status !== $value)
                                        <x-ui.button wire:click="changeStatus('{{ $value }}')" size="sm" variant="secondary">
                                            {{ $label }}
                                        </x-ui.button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-ui.card>
            @elseif ($isOwner && $complaint->isResolved())
                <x-ui.card title="Not fixed?">
                    <p class="text-sm text-secondary">If the problem is still there, reopen the ticket.</p>
                    <x-ui.button wire:click="changeStatus('reopened')" variant="secondary" size="sm" class="mt-3 w-full">
                        Reopen ticket
                    </x-ui.button>
                </x-ui.card>
            @endif

            @if ($complaint->statusLogs->isNotEmpty())
                <x-ui.card title="History" padded="false">
                    <ol class="divide-y divide-[var(--border-subtle)]">
                        @foreach ($complaint->statusLogs as $log)
                            <li class="px-5 py-3">
                                <p class="text-sm">
                                    {{ ucwords(str_replace('_', ' ', $log->to_status)) }}
                                </p>
                                <p class="text-xs text-muted">
                                    {{ $log->changedBy?->name ?? 'System' }} · {{ $log->created_at->diffForHumans() }}
                                </p>
                                @if ($log->notes)
                                    <p class="mt-1 text-xs text-secondary">{{ $log->notes }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </x-ui.card>
            @endif
        </div>
    </div>
</div>
