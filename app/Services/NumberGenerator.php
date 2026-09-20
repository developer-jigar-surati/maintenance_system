<?php

namespace App\Services;

use App\Models\NumberSequence;
use App\Models\Society;
use App\Support\SocietyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Issues the next document number in a per-society series.
 *
 * Receipts and invoices are financial records, so their numbering has to be
 * sequential and gap-free -- an auditor will ask. Counters therefore live in
 * their own table and are handed out under a row lock rather than derived from
 * max(id), which would skip numbers whenever a transaction rolls back.
 */
class NumberGenerator
{
    /** Series keys, also used as the settings key for their configuration. */
    public const INVOICE = 'invoice';
    public const RECEIPT = 'receipt';
    public const PAYMENT = 'payment';
    public const ADJUSTMENT = 'adjustment';
    public const EXPENSE = 'expense';
    public const VOUCHER = 'voucher';
    public const JOURNAL = 'journal';
    public const COMPLAINT = 'complaint';
    public const WORK_ORDER = 'work_order';
    public const GATE_PASS = 'gate_pass';
    public const BOOKING = 'booking';
    public const MEETING = 'meeting';

    /** Prefix and reset cadence used when a series is first touched. */
    private const DEFAULTS = [
        self::INVOICE => ['prefix' => 'INV', 'reset' => 'yearly', 'padding' => 5],
        self::RECEIPT => ['prefix' => 'RCP', 'reset' => 'yearly', 'padding' => 5],
        self::PAYMENT => ['prefix' => 'PAY', 'reset' => 'yearly', 'padding' => 5],
        self::ADJUSTMENT => ['prefix' => 'ADJ', 'reset' => 'yearly', 'padding' => 4],
        self::EXPENSE => ['prefix' => 'EXP', 'reset' => 'yearly', 'padding' => 5],
        self::VOUCHER => ['prefix' => 'VCH', 'reset' => 'yearly', 'padding' => 5],
        self::JOURNAL => ['prefix' => 'JV', 'reset' => 'yearly', 'padding' => 5],
        self::COMPLAINT => ['prefix' => 'TKT', 'reset' => 'never', 'padding' => 5],
        self::WORK_ORDER => ['prefix' => 'WO', 'reset' => 'yearly', 'padding' => 5],
        self::GATE_PASS => ['prefix' => 'GP', 'reset' => 'monthly', 'padding' => 4],
        self::BOOKING => ['prefix' => 'BKG', 'reset' => 'yearly', 'padding' => 5],
        self::MEETING => ['prefix' => 'MTG', 'reset' => 'yearly', 'padding' => 3],
    ];

    public function __construct(private SocietyContext $context) {}

    /**
     * Returns the next number in the series, e.g. "GREE/INV/2026-27/00042".
     *
     * Runs inside a transaction and locks the counter row, so two concurrent
     * bill runs cannot hand out the same number.
     */
    public function next(string $key, ?Society $society = null, ?Carbon $on = null): string
    {
        $society ??= $this->context->check();
        $on ??= now();

        return DB::transaction(function () use ($key, $society, $on) {
            $sequence = $this->lockSequence($key, $society);
            $periodKey = $this->periodKey($sequence->reset_frequency, $society, $on);

            // A new period restarts the counter, which is what keeps invoice
            // numbers readable year on year.
            if ($sequence->period_key !== $periodKey) {
                $sequence->period_key = $periodKey;
                $sequence->next_number = 1;
            }

            $number = (int) $sequence->next_number;
            $sequence->next_number = $number + 1;
            $sequence->save();

            return $this->format($society, $sequence, $number, $periodKey);
        });
    }

    /** Previews the next number without consuming it. */
    public function peek(string $key, ?Society $society = null, ?Carbon $on = null): string
    {
        $society ??= $this->context->check();
        $on ??= now();

        $sequence = $this->sequenceFor($key, $society);
        $periodKey = $this->periodKey($sequence->reset_frequency, $society, $on);
        $number = $sequence->period_key === $periodKey ? (int) $sequence->next_number : 1;

        return $this->format($society, $sequence, $number, $periodKey);
    }

    private function lockSequence(string $key, Society $society): NumberSequence
    {
        $this->sequenceFor($key, $society);

        return NumberSequence::query()
            ->withoutGlobalScopes()
            ->where('society_id', $society->id)
            ->where('key', $key)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function sequenceFor(string $key, Society $society): NumberSequence
    {
        $defaults = self::DEFAULTS[$key] ?? ['prefix' => strtoupper(substr($key, 0, 3)), 'reset' => 'yearly', 'padding' => 5];

        return NumberSequence::query()
            ->withoutGlobalScopes()
            ->firstOrCreate(
                ['society_id' => $society->id, 'key' => $key],
                [
                    'prefix' => $defaults['prefix'],
                    'padding' => $defaults['padding'],
                    'reset_frequency' => $defaults['reset'],
                    'next_number' => 1,
                ],
            );
    }

    /**
     * The period a number belongs to. Yearly series follow the society's
     * financial year, so April-March societies get "2026-27" rather than a
     * calendar year that splits their books.
     */
    private function periodKey(string $frequency, Society $society, Carbon $on): ?string
    {
        return match ($frequency) {
            'monthly' => $on->format('Y-m'),
            'yearly' => $this->financialYearKey($society, $on),
            default => null,
        };
    }

    private function financialYearKey(Society $society, Carbon $on): string
    {
        $startMonth = (int) ($society->financial_year_start_month ?: 4);

        if ($startMonth === 1) {
            return $on->format('Y');
        }

        $startYear = $on->month >= $startMonth ? $on->year : $on->year - 1;

        return $startYear.'-'.substr((string) ($startYear + 1), -2);
    }

    private function format(Society $society, NumberSequence $sequence, int $number, ?string $periodKey): string
    {
        $padded = str_pad((string) $number, (int) $sequence->padding, '0', STR_PAD_LEFT);

        return collect([
            $society->code,
            $sequence->prefix,
            $periodKey,
            $padded,
            $sequence->suffix ?: null,
        ])->filter()->implode('/');
    }
}
