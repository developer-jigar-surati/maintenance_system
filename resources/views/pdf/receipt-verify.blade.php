<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify receipt</title>
    @vite(['resources/css/app.css'])
</head>
<body class="surface text-primary">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-10">
        @if ($receipt === null)
            <div class="surface-card p-8 text-center">
                <span class="mx-auto mb-4 flex size-12 items-center justify-center rounded-2xl bg-[var(--color-critical-soft)] text-[var(--color-critical)]">
                    <x-ui.icon name="close" class="size-6" />
                </span>
                <h1 class="text-lg font-bold">Receipt not found</h1>
                <p class="mt-2 text-sm text-secondary">
                    No receipt matches this code. It may have been mistyped, or the document may not be genuine.
                </p>
            </div>
        @else
            <div class="surface-card p-8 text-center">
                @if ($receipt->is_cancelled)
                    <span class="mx-auto mb-4 flex size-12 items-center justify-center rounded-2xl bg-[var(--color-critical-soft)] text-[var(--color-critical)]">
                        <x-ui.icon name="alert" class="size-6" />
                    </span>
                    <h1 class="text-lg font-bold">This receipt was cancelled</h1>
                    <p class="mt-1 text-sm text-secondary">{{ $receipt->cancellation_reason }}</p>
                @else
                    <span class="mx-auto mb-4 flex size-12 items-center justify-center rounded-2xl bg-[var(--color-positive-soft)] text-[var(--color-positive)]">
                        <x-ui.icon name="check-badge" class="size-6" />
                    </span>
                    <h1 class="text-lg font-bold">Genuine receipt</h1>
                    <p class="mt-1 text-sm text-secondary">Issued by {{ $receipt->society->name }}.</p>
                @endif

                <dl class="mt-6 space-y-2 border-t border-subtle pt-5 text-left text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Receipt number</dt>
                        <dd class="numeric font-semibold">{{ $receipt->receipt_number }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Date</dt>
                        <dd class="numeric">{{ $receipt->issued_on->format('j M Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">Unit</dt>
                        <dd>{{ $receipt->unit?->label ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-subtle pt-2">
                        <dt class="font-semibold">Amount</dt>
                        <dd class="numeric text-lg font-bold">{{ \App\Support\Money::format((float) $receipt->amount) }}</dd>
                    </div>
                </dl>
            </div>
        @endif
    </main>
</body>
</html>
