<div>
    <x-ui.page-header title="Reminders & messages"
        description="When the system chases an unpaid bill, and the words it uses.">
        <x-slot:actions>
            <x-ui.badge tone="info">{{ $sentToday }} sent today</x-ui.badge>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Tabs. Two questions, one screen: how often, and in what words. --}}
    <div class="mb-6 flex gap-1 rounded-xl surface-inset p-1" role="tablist" aria-label="Reminders and messages">
        @foreach (['schedule' => 'Reminder schedule', 'templates' => 'Message wording', 'log' => 'What went out'] as $key => $label)
            <button
                type="button"
                role="tab"
                id="tab-{{ $key }}"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                aria-controls="panel-{{ $key }}"
                wire:click="$set('tab', '{{ $key }}')"
                @class([
                    'min-h-11 flex-1 rounded-lg px-4 text-sm font-semibold transition-colors',
                    'surface-raised text-primary shadow-[var(--shadow-card)]' => $tab === $key,
                    'text-secondary hover:text-primary' => $tab !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    {{-- ---------------------------------------------------------------- --}}
    @if ($tab === 'schedule')
        <div id="panel-schedule" role="tabpanel" aria-labelledby="tab-schedule" class="space-y-6">
            <x-ui.alert tone="info" title="How the schedule works">
                Each step is a number of days relative to the due date. A step at
                <strong>&minus;3</strong> writes to a resident three days before their bill is due;
                a step at <strong>7</strong> writes a week after it fell due. Reminders go out once
                a day, and only on the days named here &mdash; a resident reminded every morning
                stops reading reminders.
            </x-ui.alert>

            <x-ui.card title="Reminder steps"
                description="Chasing unpaid maintenance. Drag-free: just set the days.">
                <x-slot:actions>
                    <x-ui.select wire:model.live="event" :options="$events" class="w-56" aria-label="Which reminder schedule" />
                </x-slot:actions>

                @if ($rules === [])
                    <x-ui.empty-state icon="clock" title="No reminders are scheduled"
                        description="Nothing is sent for this event until you add at least one step." />
                @else
                    <form wire:submit="saveSchedule" class="space-y-3">
                        @foreach ($rules as $id => $rule)
                            <div class="rounded-xl border border-subtle p-4">
                                <div class="flex flex-wrap items-start gap-4">
                                    {{-- The timing, stated as a person would say it. --}}
                                    <div class="w-28 shrink-0">
                                        <p class="numeric text-2xl font-bold {{ $rule['offset_days'] < 0 ? 'text-[var(--color-positive)]' : ($rule['offset_days'] === 0 ? 'accent-text' : 'text-[var(--color-critical)]') }}">
                                            {{ $rule['offset_days'] > 0 ? '+' : '' }}{{ $rule['offset_days'] }}
                                        </p>
                                        <p class="text-xs text-secondary">
                                            @if ($rule['offset_days'] < 0)
                                                {{ abs($rule['offset_days']) }} {{ \Illuminate\Support\Str::plural('day', abs($rule['offset_days'])) }} before
                                            @elseif ($rule['offset_days'] === 0)
                                                on the due date
                                            @else
                                                {{ $rule['offset_days'] }} {{ \Illuminate\Support\Str::plural('day', $rule['offset_days']) }} after
                                            @endif
                                        </p>
                                    </div>

                                    <div class="min-w-0 flex-1 space-y-3">
                                        <x-ui.input
                                            wire:model="rules.{{ $id }}.label"
                                            label="What this step is for"
                                            placeholder="e.g. Final notice before the matter goes to the committee"
                                            :id="'label-'.$id" />

                                        <fieldset>
                                            <legend class="mb-1.5 text-sm font-medium">Send by</legend>
                                            <div class="flex flex-wrap gap-4">
                                                @foreach (['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $channel => $channelLabel)
                                                    <label class="flex min-h-11 items-center gap-2 text-sm" for="ch-{{ $id }}-{{ $channel }}">
                                                        <input
                                                            type="checkbox"
                                                            id="ch-{{ $id }}-{{ $channel }}"
                                                            value="{{ $channel }}"
                                                            wire:model="rules.{{ $id }}.channels"
                                                            class="size-5 rounded border-strong accent-[var(--accent)]"
                                                        >
                                                        {{ $channelLabel }}
                                                        @if ($channel !== 'email')
                                                            <span class="text-xs text-muted">(needs a provider)</span>
                                                        @endif
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-2">
                                        <x-ui.button
                                            size="sm"
                                            :variant="$rule['is_active'] ? 'secondary' : 'ghost'"
                                            wire:click="toggleStep({{ $id }})"
                                        >{{ $rule['is_active'] ? 'On' : 'Off' }}</x-ui.button>

                                        <x-ui.button
                                            size="icon"
                                            variant="ghost"
                                            icon="trash"
                                            wire:click="removeStep({{ $id }})"
                                            wire:confirm="Remove this reminder step?"
                                        ><span class="sr-only">Remove this step</span></x-ui.button>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="flex justify-end pt-2">
                            <x-ui.button type="submit" icon="check">Save the schedule</x-ui.button>
                        </div>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card title="Add a step" description="A day, relative to the due date.">
                <form wire:submit="addStep" class="flex flex-wrap items-end gap-3">
                    <x-ui.input
                        wire:model="newOffset"
                        name="newOffset"
                        type="number"
                        label="Days from the due date"
                        hint="Negative is before, 0 is the day itself."
                        class="w-48"
                        min="-90"
                        max="365"
                    />
                    <x-ui.input
                        wire:model="newLabel"
                        name="newLabel"
                        label="What it is for (optional)"
                        placeholder="Final notice"
                        class="min-w-64 flex-1"
                    />
                    <x-ui.button type="submit" icon="plus">Add step</x-ui.button>
                </form>
            </x-ui.card>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    @if ($tab === 'templates')
        <div id="panel-templates" role="tabpanel" aria-labelledby="tab-templates" class="space-y-4">
            <x-ui.alert tone="info" title="Placeholders">
                Anything in double braces &mdash; <code>&#123;&#123; resident_name &#125;&#125;</code> &mdash;
                is filled in when the message is sent. Each message offers its own list, and the editor
                shows you the finished text before you save.
            </x-ui.alert>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($templates as $template)
                    <x-ui.card :title="$template['name']" :description="$template['description']">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($template['customised'])
                                    <x-ui.badge tone="positive" dot>Your wording</x-ui.badge>
                                    @if ($template['updated_at'])
                                        <span class="text-xs text-muted">edited {{ $template['updated_at']->diffForHumans() }}</span>
                                    @endif
                                @else
                                    <x-ui.badge tone="neutral">Standard wording</x-ui.badge>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($template['customised'])
                                    <x-ui.button size="sm" variant="ghost"
                                        wire:click="resetTemplate('{{ $template['key'] }}')"
                                        wire:confirm="Go back to the standard wording for this message?"
                                    >Reset</x-ui.button>
                                @endif
                                <x-ui.button size="sm" variant="secondary" icon="pencil"
                                    wire:click="edit('{{ $template['key'] }}')">Edit</x-ui.button>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    @if ($tab === 'log')
        <div id="panel-log" role="tabpanel" aria-labelledby="tab-log">
            <x-ui.table
                :headers="['Message', 'To', 'Channel', 'Status', 'When']"
                :is-empty="$recent->isEmpty()"
                empty="Nothing has been sent yet"
                empty-icon="megaphone"
            >
                @foreach ($recent as $dispatch)
                    <x-ui.tr>
                        <x-ui.td label="Message" primary>
                            {{ $dispatch->subject ?: \Illuminate\Support\Str::headline($dispatch->template_key) }}
                        </x-ui.td>
                        <x-ui.td label="To">{{ $dispatch->user?->name ?? $dispatch->recipient ?? '—' }}</x-ui.td>
                        <x-ui.td label="Channel">{{ ucfirst($dispatch->channel) }}</x-ui.td>
                        <x-ui.td label="Status">
                            <x-ui.badge :tone="match ($dispatch->status) {
                                'sent' => 'positive',
                                'failed' => 'critical',
                                default => 'caution',
                            }" dot>{{ ucfirst($dispatch->status) }}</x-ui.badge>
                        </x-ui.td>
                        <x-ui.td label="When">{{ ($dispatch->sent_at ?? $dispatch->created_at)?->diffForHumans() }}</x-ui.td>
                    </x-ui.tr>
                @endforeach
            </x-ui.table>
        </div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    <x-ui.modal name="edit-template" title="Edit the wording" max-width="2xl">
        @if ($preview)
            <form wire:submit="saveTemplate" class="space-y-4">
                <x-ui.input wire:model.live.debounce.400ms="subject" name="subject" label="Subject line" />

                <x-ui.textarea wire:model.live.debounce.400ms="body" name="body" label="Message" rows="12"
                    class="font-mono text-xs" />

                @if ($preview['unknown'] !== [])
                    <x-ui.alert tone="caution" title="Unknown placeholders">
                        {{-- Braces are built from entities: a literal {{ inside an echo would be compiled. --}}
                        @foreach ($preview['unknown'] as $token)
                            <code class="font-mono">&#123;&#123; {{ $token }} &#125;&#125;</code>@if (! $loop->last), @endif
                        @endforeach
                        — these are not filled in for this message, and will come out blank.
                    </x-ui.alert>
                @endif

                <div>
                    <p class="mb-1.5 text-sm font-medium">Placeholders you can use</p>
                    <dl class="max-h-40 space-y-1 overflow-y-auto scrollbar-slim rounded-xl surface-inset p-3 text-xs">
                        @foreach ($preview['placeholders'] as $token => $meaning)
                            <div class="flex gap-2">
                                <dt class="shrink-0 font-mono accent-text">&#123;&#123; {{ $token }} &#125;&#125;</dt>
                                <dd class="text-secondary">{{ $meaning }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div>
                    <p class="mb-1.5 text-sm font-medium">What a resident will see</p>
                    <div class="rounded-xl border border-subtle surface-sunken p-4">
                        <p class="text-sm font-semibold">{{ $preview['subject'] ?: '(no subject)' }}</p>
                        <p class="mt-2 whitespace-pre-line text-sm text-secondary">{{ $preview['body'] }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'edit-template')">Cancel</x-ui.button>
                    <x-ui.button type="submit" icon="check">Save wording</x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.modal>
</div>
