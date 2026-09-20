<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Payments\PaymentRecorder;
use App\Support\Money;
use App\Support\SocietyContext;
use Illuminate\Console\Command;

/**
 * Raises bills for every plan that has come due, across every society.
 *
 * Scheduled daily: a plan decides its own cycle, so this simply asks each one
 * whether today is its day. Safe to run repeatedly -- generation is idempotent
 * per unit and period.
 */
class RunBillingCycle extends Command
{
    protected $signature = 'billing:run
        {--society= : Limit the run to one society, by id or slug}
        {--dry : Report what would be billed without writing anything}';

    protected $description = 'Generate invoices for billing plans that are due';

    public function handle(InvoiceGenerator $generator, PaymentRecorder $recorder, SocietyContext $context): int
    {
        $societies = $this->targetSocieties();

        if ($societies->isEmpty()) {
            $this->warn('No active societies to bill.');

            return self::SUCCESS;
        }

        $totalInvoices = 0;
        $totalValue = 0.0;

        foreach ($societies as $society) {
            $context->set($society);

            $plans = $generator->duePlans($society);

            if ($plans->isEmpty()) {
                continue;
            }

            foreach ($plans as $plan) {
                if ($this->option('dry')) {
                    $this->line("  [dry] {$society->name}: {$plan->name} is due ({$plan->next_run_on->toDateString()})");

                    continue;
                }

                $result = $generator->run($plan);

                // A unit that paid in advance should not be shown as owing.
                foreach ($result['invoices'] as $invoice) {
                    $recorder->applyCreditTo($invoice);
                }

                $totalInvoices += $result['created'];
                $totalValue += $result['total'];

                $this->info(sprintf(
                    '  %s — %s: %d invoices, %s',
                    $society->name,
                    $plan->name,
                    $result['created'],
                    Money::format($result['total']),
                ));
            }
        }

        $context->forget();

        $this->newLine();
        $this->info(sprintf(
            'Billing run complete: %d invoices totalling %s.',
            $totalInvoices,
            Money::format($totalValue),
        ));

        return self::SUCCESS;
    }

    private function targetSocieties()
    {
        $query = Society::query()->where('status', 'active');

        if ($id = $this->option('society')) {
            $query->where(fn ($q) => $q->where('id', $id)->orWhere('slug', $id));
        }

        return $query->get();
    }
}
