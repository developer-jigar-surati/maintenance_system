<?php

namespace App\Services\Messaging;

use App\Models\Invoice;
use App\Models\ReminderRule;
use App\Models\Society;
use App\Support\MessageCatalogue;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Walks a society's reminder schedule and nudges whoever still owes money.
 *
 * The schedule is the society's own: rows in reminder_rules rather than a
 * constant in this file, because "three days before, on the day, then weekly"
 * is a committee's decision and differs between societies. A run on a day the
 * schedule does not name sends nothing at all, which is the point -- residents
 * who are reminded daily stop reading reminders.
 */
class InvoiceReminders
{
    public function __construct(private Messenger $messenger) {}

    /**
     * @return array{sent: int, skipped: int, rules: int}
     */
    public function run(Society $society, ?Carbon $on = null): array
    {
        $on ??= now();
        $sent = 0;
        $skipped = 0;

        $rules = ReminderRule::query()
            ->forSociety($society)
            ->active()
            ->forEvent('invoice_due')
            ->orderBy('offset_days')
            ->get();

        foreach ($rules as $rule) {
            // A rule at -3 fires today for bills due in three days; a rule at
            // 7 fires for bills that fell due a week ago.
            $target = $on->copy()->subDays($rule->offset_days)->toDateString();

            $invoices = Invoice::query()
                ->forSociety($society)
                ->open()
                ->whereDate('due_date', $target)
                ->with(['unit.block', 'unit.activeResidents.user'])
                ->get();

            foreach ($invoices as $invoice) {
                $recipient = $invoice->unit?->billingContact()?->user;

                if ($recipient === null) {
                    $skipped++;

                    continue;
                }

                foreach ($this->channels($rule) as $channel) {
                    $dispatch = $this->messenger->send(
                        society: $society,
                        templateKey: $rule->template_key ?: MessageCatalogue::PAYMENT_REMINDER,
                        recipient: $recipient,
                        data: $this->data($invoice, $rule->offset_days),
                        channel: $channel,
                        related: $invoice,
                        // One nudge per bill, per step, per channel -- however
                        // many times the command is run in a day.
                        dedupeKey: "reminder:{$invoice->id}:{$rule->offset_days}:{$channel}:{$on->toDateString()}",
                    );

                    $dispatch === null ? $skipped++ : $sent++;
                }
            }

            $rule->forceFill(['last_run_at' => now()])->save();
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'rules' => $rules->count()];
    }

    /** @return array<int, string> */
    private function channels(ReminderRule $rule): array
    {
        $channels = array_filter((array) ($rule->channels ?: ['email']));

        return $channels === [] ? ['email'] : array_values($channels);
    }

    /**
     * The values the reminder templates may use.
     *
     * @return array<string, string>
     */
    public function data(Invoice $invoice, int $offsetDays): array
    {
        $lateFee = (float) $invoice->late_fee_total;

        return [
            'unit_label' => (string) $invoice->unit?->label,
            'invoice_number' => (string) $invoice->invoice_number,
            'invoice_period' => $this->period($invoice),
            'amount_due' => Money::format((float) $invoice->balance),
            'amount_total' => Money::format((float) $invoice->total),
            'due_date' => $invoice->due_date?->format('j F Y') ?? '',
            'due_phrase' => $this->duePhrase($offsetDays),
            'days_overdue' => (string) max(0, $offsetDays),
            'late_fee_total' => Money::format($lateFee),
            'late_fee_line' => $lateFee > 0
                ? 'Interest of '.Money::format($lateFee).' has been added so far.'
                : '',
            'invoice_link' => route('invoices.show', $invoice),
        ];
    }

    /**
     * How the message refers to the timing. Written out rather than left as a
     * number so a template reads "due in 3 days", not "due -3".
     */
    private function duePhrase(int $offsetDays): string
    {
        return match (true) {
            $offsetDays < 0 => 'due in '.abs($offsetDays).' '.(abs($offsetDays) === 1 ? 'day' : 'days'),
            $offsetDays === 0 => 'due today',
            default => 'overdue by '.$offsetDays.' '.($offsetDays === 1 ? 'day' : 'days'),
        };
    }

    private function period(Invoice $invoice): string
    {
        if ($invoice->period_start === null) {
            return '';
        }

        return $invoice->period_end && ! $invoice->period_end->isSameMonth($invoice->period_start)
            ? $invoice->period_start->format('M Y').' – '.$invoice->period_end->format('M Y')
            : $invoice->period_start->format('F Y');
    }
}
