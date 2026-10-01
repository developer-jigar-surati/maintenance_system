<?php

namespace Tests\Unit;

use App\Support\Money;
use Tests\TestCase;

/**
 * Indian digit grouping, which is what residents read on every other bill
 * they receive.
 *
 * Extends the application test case rather than a bare PHPUnit one: the
 * formatter reads the configured currency, so it needs the container.
 */
class MoneyTest extends TestCase
{
    public function test_grouping_follows_the_lakh_convention(): void
    {
        $this->assertSame('100.00', Money::group(100));
        $this->assertSame('1,000.00', Money::group(1000));
        $this->assertSame('12,345.00', Money::group(12345));
        $this->assertSame('1,23,456.00', Money::group(123456));
        $this->assertSame('12,34,567.00', Money::group(1234567));
        $this->assertSame('1,23,45,678.00', Money::group(12345678));
    }

    public function test_negative_amounts_keep_their_sign(): void
    {
        $this->assertSame('-₹1,23,456.00', Money::format(-123456));
    }

    public function test_compact_form_uses_lakh_and_crore(): void
    {
        $this->assertSame('₹999', Money::compact(999));
        $this->assertSame('₹12.5K', Money::compact(12500));
        $this->assertSame('₹1.5L', Money::compact(150000));
        $this->assertSame('₹2.5Cr', Money::compact(25000000));
    }

    public function test_amount_in_words_matches_the_receipt_convention(): void
    {
        $this->assertSame('Rupees One Thousand Only', Money::inWords(1000));
        $this->assertSame('Rupees Twelve Thousand Three Hundred and Forty Five Only', Money::inWords(12345));
        $this->assertSame('Rupees One Lakh Only', Money::inWords(100000));
        $this->assertSame('Rupees Zero Only', Money::inWords(0));
    }

    public function test_paise_are_spelled_out_when_present(): void
    {
        $this->assertSame('Rupees One Hundred and Fifty Paise Only', Money::inWords(100.50));
    }
}
