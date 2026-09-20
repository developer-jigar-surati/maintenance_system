<?php

use App\Console\Commands\AccrueLateFees;
use App\Console\Commands\EscalateBreachedTickets;
use App\Console\Commands\RaiseScheduledMaintenance;
use App\Console\Commands\RunBillingCycle;
use App\Console\Commands\SendPaymentReminders;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| This is the automation the system exists for: bills go out, interest
| accrues, reminders are sent and routine maintenance is raised without
| anyone having to remember. Every command is idempotent, so a missed run
| catches up and a double run changes nothing.
|
| One cron entry drives all of it:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Early morning, before the office opens.
Schedule::command(RunBillingCycle::class)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// After billing, so a bill raised today is not immediately penalised.
Schedule::command(AccrueLateFees::class)
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Mid-morning, when a reminder is more likely to be read.
Schedule::command(SendPaymentReminders::class)
    ->dailyAt('10:00')
    ->weekdays()
    ->withoutOverlapping()
    ->onOneServer();

// Hourly, so an SLA breach is noticed within the hour rather than the day.
Schedule::command(EscalateBreachedTickets::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command(RaiseScheduledMaintenance::class)
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

// Housekeeping.
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('auth:clear-resets')->daily();
