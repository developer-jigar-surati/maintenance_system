<?php

namespace Tests\Feature\Billing;

use App\Models\Society;
use App\Services\NumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Document numbering. An auditor will ask whether the receipt series has
 * gaps, so this is the one place a "close enough" answer is not acceptable.
 */
class NumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_increment_without_gaps(): void
    {
        $society = $this->makeSociety();
        $numbers = app(NumberGenerator::class);

        $issued = collect(range(1, 10))
            ->map(fn () => $numbers->next(NumberGenerator::RECEIPT, $society))
            ->map(fn ($n) => (int) substr($n, strrpos($n, '/') + 1))
            ->all();

        $this->assertSame(range(1, 10), $issued);
    }

    public function test_each_society_has_its_own_series(): void
    {
        $first = $this->makeSociety();
        $second = $this->makeSociety();
        $numbers = app(NumberGenerator::class);

        $numbers->next(NumberGenerator::INVOICE, $first);
        $numbers->next(NumberGenerator::INVOICE, $first);

        $secondFirst = $numbers->next(NumberGenerator::INVOICE, $second);

        $this->assertStringEndsWith('00001', $secondFirst);
        $this->assertStringStartsWith($second->code, $secondFirst);
    }

    public function test_series_are_independent_of_each_other(): void
    {
        $society = $this->makeSociety();
        $numbers = app(NumberGenerator::class);

        $numbers->next(NumberGenerator::INVOICE, $society);
        $numbers->next(NumberGenerator::INVOICE, $society);

        $this->assertStringEndsWith('00001', $numbers->next(NumberGenerator::RECEIPT, $society));
    }

    public function test_the_number_carries_the_financial_year(): void
    {
        $society = $this->makeSociety(['financial_year_start_month' => 4]);

        // April is inside 2026-27 for an April-March society.
        $this->travelTo(now()->setDate(2026, 5, 10));
        $inYear = app(NumberGenerator::class)->next(NumberGenerator::INVOICE, $society);
        $this->assertStringContainsString('2026-27', $inYear);

        // February falls in the previous financial year.
        $this->travelTo(now()->setDate(2027, 2, 10));
        $nextYear = app(NumberGenerator::class)->next(NumberGenerator::INVOICE, $society);
        $this->assertStringContainsString('2026-27', $nextYear);

        // April starts a new one, and the counter restarts.
        $this->travelTo(now()->setDate(2027, 4, 2));
        $newYear = app(NumberGenerator::class)->next(NumberGenerator::INVOICE, $society);
        $this->assertStringContainsString('2027-28', $newYear);
        $this->assertStringEndsWith('00001', $newYear);

        $this->travelBack();
    }

    public function test_a_calendar_year_society_uses_a_plain_year(): void
    {
        $society = $this->makeSociety(['financial_year_start_month' => 1]);

        $this->travelTo(now()->setDate(2026, 7, 1));
        $number = app(NumberGenerator::class)->next(NumberGenerator::INVOICE, $society);

        $this->assertStringContainsString('2026', $number);
        $this->assertStringNotContainsString('2026-27', $number);

        $this->travelBack();
    }

    public function test_peeking_does_not_consume_a_number(): void
    {
        $society = $this->makeSociety();
        $numbers = app(NumberGenerator::class);

        $peeked = $numbers->peek(NumberGenerator::RECEIPT, $society);
        $taken = $numbers->next(NumberGenerator::RECEIPT, $society);

        $this->assertSame($peeked, $taken);
    }

    public function test_the_ticket_series_never_resets(): void
    {
        $society = $this->makeSociety();
        $numbers = app(NumberGenerator::class);

        $this->travelTo(now()->setDate(2026, 5, 1));
        $numbers->next(NumberGenerator::COMPLAINT, $society);

        $this->travelTo(now()->setDate(2028, 5, 1));
        $later = $numbers->next(NumberGenerator::COMPLAINT, $society);

        // Configured to never reset, so it continues rather than restarting.
        $this->assertStringEndsWith('00002', $later);

        $this->travelBack();
    }
}
