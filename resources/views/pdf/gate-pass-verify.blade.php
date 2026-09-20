<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify gate pass</title>
    @vite(['resources/css/app.css'])
</head>
<body class="surface text-primary">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-10">
        @if ($pass === null)
            <div class="surface-card p-8 text-center">
                <h1 class="text-lg font-bold">Pass not found</h1>
                <p class="mt-2 text-sm text-secondary">This code does not match any gate pass.</p>
            </div>
        @else
            @php $usable = $pass->isUsable(); @endphp
            <div class="surface-card p-8 text-center">
                <span class="mx-auto mb-4 flex size-12 items-center justify-center rounded-2xl
                    {{ $usable ? 'bg-[var(--color-positive-soft)] text-[var(--color-positive)]' : 'bg-[var(--color-critical-soft)] text-[var(--color-critical)]' }}">
                    <x-ui.icon :name="$usable ? 'check-badge' : 'alert'" class="size-6" />
                </span>

                <h1 class="text-lg font-bold">
                    {{ $usable ? 'Valid — allow through' : 'Not valid right now' }}
                </h1>
                <p class="mt-1 text-sm text-secondary">
                    {{ $pass->typeLabel() }} · {{ $pass->society->name }}
                </p>

                <dl class="mt-6 space-y-2 border-t border-subtle pt-5 text-left text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Pass number</dt>
                        <dd class="numeric font-semibold">{{ $pass->pass_number }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Issued to</dt>
                        <dd>{{ $pass->issued_to_name }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Unit</dt>
                        <dd>{{ $pass->unit?->label ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Valid until</dt>
                        <dd class="numeric">{{ $pass->valid_to->format('j M Y, g:i A') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Status</dt>
                        <dd><x-ui.status :value="$pass->status" /></dd>
                    </div>
                </dl>

                @if ($pass->items)
                    <div class="mt-5 border-t border-subtle pt-5 text-left">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted">Items</p>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($pass->items as $item)
                                <li>{{ is_array($item) ? ($item['name'] ?? '') . ' × ' . ($item['quantity'] ?? 1) : $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </main>
</body>
</html>
