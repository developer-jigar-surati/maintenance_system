<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminds a resident about a bill. The tone changes with how late it is:
 * a nudge before the due date, a firmer note once interest is accruing.
 */
class PaymentReminder extends Notification
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public int $daysPastDue = 0,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $society = $this->invoice->resolveSociety();
        $amount = Money::format((float) $this->invoice->balance);

        $mail = (new MailMessage)
            ->subject($this->subject($society->name))
            ->greeting("Hello {$notifiable->name},");

        if ($this->daysPastDue < 0) {
            $mail->line(sprintf(
                'Your maintenance bill %s for %s is due on %s.',
                $this->invoice->invoice_number,
                $this->invoice->unit?->label,
                $this->invoice->due_date->format('j F Y'),
            ));
        } elseif ($this->daysPastDue === 0) {
            $mail->line(sprintf(
                'Your maintenance bill %s for %s is due today.',
                $this->invoice->invoice_number,
                $this->invoice->unit?->label,
            ));
        } else {
            $mail->line(sprintf(
                'Your maintenance bill %s for %s was due on %s, %d days ago.',
                $this->invoice->invoice_number,
                $this->invoice->unit?->label,
                $this->invoice->due_date->format('j F Y'),
                $this->daysPastDue,
            ));

            if ((float) $this->invoice->late_fee_total > 0) {
                $mail->line(sprintf(
                    'Interest of %s has been added so far.',
                    Money::format((float) $this->invoice->late_fee_total),
                ));
            }
        }

        return $mail
            ->line("**Amount outstanding: {$amount}**")
            ->action('View the bill', route('invoices.show', $this->invoice))
            ->line('If you have already paid, please ignore this message — your receipt is available in the app.')
            ->salutation("— {$society->name}");
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_reminder',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'unit' => $this->invoice->unit?->label,
            'balance' => (float) $this->invoice->balance,
            'due_date' => $this->invoice->due_date->toDateString(),
            'days_past_due' => $this->daysPastDue,
        ];
    }

    private function subject(string $society): string
    {
        return match (true) {
            $this->daysPastDue < 0 => "Maintenance due soon — {$society}",
            $this->daysPastDue === 0 => "Maintenance due today — {$society}",
            default => "Maintenance overdue by {$this->daysPastDue} days — {$society}",
        };
    }
}
