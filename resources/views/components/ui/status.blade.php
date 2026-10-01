@props(['value'])

@php
    /* Maps every status string used across the app onto a tone and a readable
       label, so one component covers invoices, payments, tickets and bookings. */
    [$tone, $label] = match ($value) {
        'paid', 'completed', 'approved', 'resolved', 'closed', 'active', 'passed', 'verified', 'used', 'exited'
            => ['positive', ucwords(str_replace('_', ' ', $value))],
        'overdue', 'failed', 'cancelled', 'rejected', 'denied', 'bounced', 'expired', 'written_off', 'blacklisted'
            => ['critical', ucwords(str_replace('_', ' ', $value))],
        'pending', 'awaiting_approval', 'pending_approval', 'draft', 'on_hold', 'partially_paid', 'reopened', 'deferred'
            => ['caution', ucwords(str_replace('_', ' ', $value))],
        'issued', 'open', 'assigned', 'in_progress', 'scheduled', 'inside', 'expected'
            => ['info', ucwords(str_replace('_', ' ', $value))],
        default => ['neutral', ucwords(str_replace('_', ' ', (string) $value))],
    };
@endphp

<x-ui.badge :tone="$tone" dot {{ $attributes }}>{{ $label }}</x-ui.badge>
