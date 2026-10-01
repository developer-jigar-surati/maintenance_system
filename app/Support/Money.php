<?php

namespace App\Support;

/**
 * Money formatting.
 *
 * Indian societies read amounts in the lakh/crore grouping (1,23,456.00), not
 * the Western thousands grouping, so that is the default. Other locales fall
 * back to plain thousands separators.
 */
class Money
{
    public static function format(float $amount, ?string $symbol = null, bool $decimals = true): string
    {
        $symbol ??= config('app.currency_symbol', '₹');
        $negative = $amount < 0;
        $formatted = self::group(abs($amount), $decimals);

        return ($negative ? '-' : '').$symbol.$formatted;
    }

    /** Groups digits the Indian way: last three, then pairs. */
    public static function group(float $amount, bool $decimals = true): string
    {
        if (config('app.currency', 'INR') !== 'INR') {
            return number_format($amount, $decimals ? 2 : 0);
        }

        $fixed = number_format($amount, $decimals ? 2 : 0, '.', '');
        [$whole, $fraction] = array_pad(explode('.', $fixed), 2, null);

        if (strlen($whole) <= 3) {
            return $fraction === null ? $whole : "{$whole}.{$fraction}";
        }

        $lastThree = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $grouped = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$lastThree;

        return $fraction === null ? $grouped : "{$grouped}.{$fraction}";
    }

    /** Compact form for dashboard tiles: 12.5L, 1.2Cr. */
    public static function compact(float $amount, ?string $symbol = null): string
    {
        $symbol ??= config('app.currency_symbol', '₹');
        $abs = abs($amount);
        $sign = $amount < 0 ? '-' : '';

        if (config('app.currency', 'INR') !== 'INR') {
            return match (true) {
                $abs >= 1_000_000 => $sign.$symbol.round($abs / 1_000_000, 1).'M',
                $abs >= 1_000 => $sign.$symbol.round($abs / 1_000, 1).'K',
                default => $sign.$symbol.number_format($abs, 0),
            };
        }

        return match (true) {
            $abs >= 10_000_000 => $sign.$symbol.rtrim(rtrim(number_format($abs / 10_000_000, 2), '0'), '.').'Cr',
            $abs >= 100_000 => $sign.$symbol.rtrim(rtrim(number_format($abs / 100_000, 2), '0'), '.').'L',
            $abs >= 1_000 => $sign.$symbol.rtrim(rtrim(number_format($abs / 1_000, 1), '0'), '.').'K',
            default => $sign.$symbol.number_format($abs, 0),
        };
    }

    /** Amount in words, as printed on a receipt: "Rupees One Thousand Only". */
    public static function inWords(float $amount): string
    {
        $rupees = (int) floor(abs($amount));
        $paise = (int) round((abs($amount) - $rupees) * 100);

        $words = self::numberToWords($rupees);
        $result = 'Rupees '.$words;

        if ($paise > 0) {
            $result .= ' and '.self::numberToWords($paise).' Paise';
        }

        return $result.' Only';
    }

    private static function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $below100 = function (int $n) use ($units, $tens): string {
            if ($n < 20) {
                return $units[$n];
            }

            return trim($tens[intdiv($n, 10)].' '.$units[$n % 10]);
        };

        $parts = [];

        // Indian place values: crore, lakh, thousand, hundred.
        foreach ([10_000_000 => 'Crore', 100_000 => 'Lakh', 1_000 => 'Thousand', 100 => 'Hundred'] as $value => $name) {
            if ($number >= $value) {
                $count = intdiv($number, $value);
                $parts[] = ($value >= 1000 ? self::numberToWords($count) : $below100($count)).' '.$name;
                $number %= $value;
            }
        }

        if ($number > 0) {
            $parts[] = ($parts !== [] ? 'and ' : '').$below100($number);
        }

        return implode(' ', $parts);
    }
}
