<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Services\Helpdesk\ComplaintService;
use App\Support\SocietyContext;
use Illuminate\Console\Command;

/**
 * Raises the escalation level of tickets that have missed their SLA, so a
 * complaint cannot quietly sit unattended.
 */
class EscalateBreachedTickets extends Command
{
    protected $signature = 'helpdesk:escalate';

    protected $description = 'Escalate helpdesk tickets that have breached their SLA';

    public function handle(ComplaintService $helpdesk, SocietyContext $context): int
    {
        $escalated = 0;

        foreach (Society::where('status', 'active')->get() as $society) {
            $context->set($society);

            $tickets = $helpdesk->escalateBreached($society);

            if ($tickets->isNotEmpty()) {
                $this->warn("  {$society->name}: {$tickets->count()} tickets escalated");
                $escalated += $tickets->count();
            }
        }

        $context->forget();

        $this->info("Escalated {$escalated} tickets.");

        return self::SUCCESS;
    }
}
