<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Society;
use App\Notifications\PaymentReminder;
use App\Support\SocietyContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Nudges residents whose bills are due soon or already overdue.
 *
 * Reminders go out on a fixed ladder relative to the due date rather than
 * every day, so residents are not trained to ignore them.
 */
class SendPaymentReminders extends Command
{
    protected $signature = 'billing:remind {--society= : Limit to one society}';

    protected $description = 'Notify residents about bills that are due or overdue';

    /** Days relative to the due date on which a reminder is sent. */
    private const LADDER = [-3, 0, 7, 21, 45];

    public function handle(SocietyContext $context): int
    {
        $sent = 0;

        $societies = Society::query()
            ->where('status', 'active')
            ->when($this->option('society'), fn ($q, $id) => $q->where('id', $id)->orWhere('slug', $id))
            ->get();

        foreach ($societies as $society) {
            $context->set($society);

            foreach (self::LADDER as $offset) {
                $target = now()->copy()->subDays($offset)->toDateString();

                $invoices = Invoice::query()
                    ->open()
                    ->whereDate('due_date', $target)
                    ->with(['unit.activeResidents.user', 'society'])
                    ->get();

                foreach ($invoices as $invoice) {
                    $recipient = $invoice->unit?->billingContact()?->user;

                    if ($recipient === null) {
                        continue;
                    }

                    Notification::send($recipient, new PaymentReminder($invoice, $offset));
                    $sent++;
                }
            }
        }

        $context->forget();

        $this->info("Sent {$sent} reminders.");

        return self::SUCCESS;
    }
}
