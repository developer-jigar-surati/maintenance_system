<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Services\Messaging\InvoiceReminders;
use App\Support\SocietyContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Nudges residents whose bills are due soon or already overdue.
 *
 * Which days count is the society's own schedule, edited in Settings, so this
 * command runs daily and does nothing at all on a day the schedule does not
 * name. Sending is idempotent, so running it twice sends nothing twice.
 */
class SendPaymentReminders extends Command
{
    protected $signature = 'billing:remind
        {--society= : Limit to one society}
        {--date= : Run as though it were this date, for checking a schedule}';

    protected $description = 'Notify residents about bills that are due or overdue';

    public function handle(SocietyContext $context, InvoiceReminders $reminders): int
    {
        $on = $this->option('date') ? Carbon::parse($this->option('date')) : now();

        $societies = Society::query()
            ->where('status', 'active')
            ->when($this->option('society'), fn ($q, $id) => $q->where(
                fn ($w) => $w->where('id', $id)->orWhere('slug', $id)
            ))
            ->get();

        $totals = ['sent' => 0, 'skipped' => 0];

        foreach ($societies as $society) {
            $context->set($society);

            $result = $reminders->run($society, $on);

            $totals['sent'] += $result['sent'];
            $totals['skipped'] += $result['skipped'];

            if ($result['rules'] === 0) {
                $this->warn("{$society->name} has no reminder schedule; nothing was sent.");
            }
        }

        $context->forget();

        $this->info("Sent {$totals['sent']} reminders ({$totals['skipped']} skipped as already sent or with nobody to write to).");

        return self::SUCCESS;
    }
}
