<?php

namespace App\Console\Commands;

use App\Models\MaintenanceSchedule;
use App\Models\Society;
use App\Models\WorkOrder;
use App\Services\NumberGenerator;
use App\Support\SocietyContext;
use Illuminate\Console\Command;

/**
 * Turns preventive-maintenance schedules into work orders shortly before each
 * occurrence falls due, so routine servicing is not forgotten between AGMs.
 */
class RaiseScheduledMaintenance extends Command
{
    protected $signature = 'maintenance:raise-work-orders';

    protected $description = 'Create work orders for preventive maintenance that is coming due';

    public function handle(NumberGenerator $numbers, SocietyContext $context): int
    {
        $created = 0;

        foreach (Society::where('status', 'active')->get() as $society) {
            $context->set($society);

            $due = MaintenanceSchedule::query()
                ->active()
                ->where('auto_create_work_order', true)
                ->whereNotNull('next_due_on')
                ->with(['asset', 'vendor'])
                ->get()
                ->filter(fn (MaintenanceSchedule $s) => $s->isDueForWorkOrder());

            foreach ($due as $schedule) {
                // Skip when this occurrence already has an open work order.
                $exists = WorkOrder::query()
                    ->where('maintenance_schedule_id', $schedule->id)
                    ->whereDate('scheduled_for', $schedule->next_due_on)
                    ->exists();

                if ($exists) {
                    continue;
                }

                WorkOrder::create([
                    'society_id' => $society->id,
                    'work_order_number' => $numbers->next(NumberGenerator::WORK_ORDER, $society),
                    'source' => 'schedule',
                    'maintenance_schedule_id' => $schedule->id,
                    'asset_id' => $schedule->asset_id,
                    'vendor_id' => $schedule->vendor_id,
                    'assigned_to' => $schedule->assigned_to,
                    'title' => $schedule->title,
                    'description' => $schedule->description,
                    'priority' => 'medium',
                    'status' => $schedule->assigned_to ? 'assigned' : 'open',
                    'scheduled_for' => $schedule->next_due_on,
                    'estimated_cost' => $schedule->estimated_cost,
                    'checklist' => $schedule->checklist,
                ]);

                $created++;
                $this->info("  {$society->name}: {$schedule->title}");
            }
        }

        $context->forget();

        $this->info("Raised {$created} work orders.");

        return self::SUCCESS;
    }
}
