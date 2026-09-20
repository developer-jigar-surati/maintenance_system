<div>
    <x-ui.page-header title="Billing plans" description="How often bills go out, and what they carry.">
        <x-slot:actions>
            <x-ui.button :href="route('charge-heads.index')" variant="secondary" icon="tag">Charge heads</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($plans->isEmpty())
        <x-ui.card>
            <x-ui.empty-state
                icon="calendar-repeat"
                title="No billing plans yet"
                description="A plan decides the billing cycle, the due date, and which charge heads appear on each bill."
            />
        </x-ui.card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($plans as $plan)
                <x-ui.card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold">{{ $plan->name }}</h2>
                            <p class="mt-0.5 text-sm text-secondary">
                                {{ $plan->cycleLabel() }} · due {{ $plan->due_after_days }} days after issue
                            </p>
                        </div>
                        <x-ui.status :value="$plan->is_active ? 'active' : 'inactive'" />
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-subtle pt-4 text-sm">
                        <div>
                            <dt class="text-xs text-muted">Next run</dt>
                            <dd class="numeric mt-0.5 font-medium">
                                {{ $plan->next_run_on?->format('j M Y') ?? 'Not scheduled' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Invoices raised</dt>
                            <dd class="numeric mt-0.5 font-medium">{{ number_format($plan->invoices_count) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Generation</dt>
                            <dd class="mt-0.5 font-medium">{{ $plan->auto_generate ? 'Automatic' : 'Manual' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Late fee</dt>
                            <dd class="mt-0.5 font-medium">
                                {{ $plan->lateFeeRule?->describe() ?? 'None' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-4 border-t border-subtle pt-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted">Charge heads</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse ($plan->chargeHeads as $head)
                                <x-ui.badge tone="neutral">{{ $head->name }}</x-ui.badge>
                            @empty
                                <span class="text-sm text-[var(--color-critical)]">None yet — this plan cannot bill.</span>
                            @endforelse
                        </div>
                    </div>

                    @canany([\App\Enums\Permission::INVOICE_GENERATE, \App\Enums\Permission::BILLING_MANAGE])
                        <div class="mt-5 flex flex-wrap gap-2 border-t border-subtle pt-4">
                            @can(\App\Enums\Permission::INVOICE_GENERATE)
                                <x-ui.button
                                    size="sm"
                                    icon="arrow-path"
                                    wire:click="runNow({{ $plan->id }})"
                                    wire:confirm="Raise bills for every eligible unit under this plan?"
                                    wire:loading.attr="disabled"
                                    wire:target="runNow({{ $plan->id }})"
                                >
                                    <span wire:loading.remove wire:target="runNow({{ $plan->id }})">Run now</span>
                                    <span wire:loading wire:target="runNow({{ $plan->id }})">Running&hellip;</span>
                                </x-ui.button>
                            @endcan
                            @can(\App\Enums\Permission::BILLING_MANAGE)
                                <x-ui.button size="sm" variant="secondary" wire:click="toggle({{ $plan->id }})">
                                    {{ $plan->is_active ? 'Pause' : 'Activate' }}
                                </x-ui.button>
                            @endcan
                        </div>
                    @endcanany
                </x-ui.card>
            @endforeach
        </div>
    @endif
</div>
