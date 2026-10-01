<?php

namespace App\Services\Billing;

use App\Models\ChargeHead;
use App\Models\ChargeRate;
use App\Models\Society;
use Illuminate\Support\Facades\DB;

/**
 * Writes a committee's answer about a charge into rates.
 *
 * One place, because the setup wizard and the charge heads screen ask the
 * same question and must store the answer identically. Two copies of this
 * would drift, and the drift would only show up as a wrong bill.
 *
 * Switching from one shape to another clears the previous shape first. A
 * society that moves from per building to a single amount must not be left
 * with the old building rates quietly still billing people.
 */
class RateWriter
{
    /**
     * @param  array{basis: string, flat?: mixed, blocks?: array, sizes?: array, grid?: array}  $answer
     */
    public function write(Society $society, ChargeHead $head, array $answer): void
    {
        DB::transaction(function () use ($society, $head, $answer) {
            ChargeRate::query()->forSociety($society)->where('charge_head_id', $head->id)->delete();

            $basis = $answer['basis'] ?? 'flat';

            $head->forceFill([
                'basis' => $basis === 'by_area' ? 'per_sqft' : 'fixed_per_unit',
                // The default is what a flat falls back to when no slice
                // names it: the typed amount, or the average of the slices.
                'default_rate' => $this->fallbackRate($basis, $answer),
            ])->save();

            match ($basis) {
                'by_block' => $this->writeBlocks($society, $head, $answer['blocks'] ?? []),
                'by_size' => $this->writeSizes($society, $head, $answer['sizes'] ?? []),
                'by_block_and_size' => $this->writeGrid($society, $head, $answer['grid'] ?? []),
                default => null,
            };
        });
    }

    private function fallbackRate(string $basis, array $answer): float
    {
        return match ($basis) {
            'flat', 'by_area' => (float) ($answer['flat'] ?? 0),
            'by_block' => (float) collect($answer['blocks'] ?? [])->filter()->avg(),
            'by_size' => (float) collect($answer['sizes'] ?? [])->filter()->avg(),
            'by_block_and_size' => (float) collect($answer['grid'] ?? [])->filter()->avg(),
            default => 0.0,
        };
    }

    private function writeBlocks(Society $society, ChargeHead $head, array $amounts): void
    {
        foreach ($amounts as $blockId => $amount) {
            if ((float) $amount > 0) {
                ChargeRate::create([
                    'society_id' => $society->id,
                    'charge_head_id' => $head->id,
                    'scope' => 'block',
                    'block_id' => (int) $blockId,
                    'rate' => (float) $amount,
                ]);
            }
        }
    }

    private function writeSizes(Society $society, ChargeHead $head, array $amounts): void
    {
        foreach ($amounts as $configuration => $amount) {
            if ((float) $amount > 0) {
                ChargeRate::create([
                    'society_id' => $society->id,
                    'charge_head_id' => $head->id,
                    'scope' => 'configuration',
                    'configuration' => $configuration,
                    'rate' => (float) $amount,
                ]);
            }
        }
    }

    /** Keys are "blockId|configuration", which is how the grid is laid out. */
    private function writeGrid(Society $society, ChargeHead $head, array $amounts): void
    {
        foreach ($amounts as $cell => $amount) {
            [$blockId, $configuration] = array_pad(explode('|', (string) $cell, 2), 2, null);

            if ((float) $amount <= 0 || $configuration === null || $configuration === '') {
                continue;
            }

            ChargeRate::create([
                'society_id' => $society->id,
                'charge_head_id' => $head->id,
                'scope' => 'block_configuration',
                'block_id' => (int) $blockId,
                'configuration' => $configuration,
                'rate' => (float) $amount,
            ]);
        }
    }
}
