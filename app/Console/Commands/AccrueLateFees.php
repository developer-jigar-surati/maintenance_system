<?php

namespace App\Console\Commands;

use App\Models\LateFeeRule;
use App\Models\Society;
use App\Services\Billing\LateFeeCalculator;
use App\Support\Money;
use App\Support\SocietyContext;
use Illuminate\Console\Command;

/**
 * Posts interest on overdue bills.
 *
 * Idempotent by construction: each accrual carries a key unique per invoice
 * and period, so running this twice in a day cannot charge a resident twice.
 */
class AccrueLateFees extends Command
{
    protected $signature = 'billing:accrue-late-fees {--society= : Limit to one society}';

    protected $description = 'Accrue interest and penalties on overdue invoices';

    public function handle(LateFeeCalculator $calculator, SocietyContext $context): int
    {
        $societies = Society::query()
            ->where('status', 'active')
            ->when($this->option('society'), fn ($q, $id) => $q->where('id', $id)->orWhere('slug', $id))
            ->get();

        $posted = 0;
        $amount = 0.0;

        foreach ($societies as $society) {
            $context->set($society);

            $rules = LateFeeRule::where('is_active', true)->get();

            foreach ($rules as $rule) {
                $result = $calculator->accrueForSociety($society, $rule);

                if ($result['posted'] > 0) {
                    $this->info(sprintf(
                        '  %s - %s: %d invoices, %s',
                        $society->name,
                        $rule->name,
                        $result['posted'],
                        Money::format($result['amount']),
                    ));
                }

                $posted += $result['posted'];
                $amount += $result['amount'];
            }
        }

        $context->forget();

        $this->info(sprintf('Accrued %s across %d invoices.', Money::format($amount), $posted));

        return self::SUCCESS;
    }
}
