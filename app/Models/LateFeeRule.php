<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Interest and penalty policy for overdue bills.
 *
 * The arithmetic lives in LateFeeCalculator; this model holds the policy and
 * answers whether a given invoice is in scope at all.
 */
class LateFeeRule extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'grace_days' => 'integer',
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'applies_above_amount' => 'decimal:2',
            'slabs' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function chargeHead(): BelongsTo
    {
        return $this->belongsTo(ChargeHead::class);
    }

    /** The date interest starts running, i.e. the due date plus any grace. */
    public function chargeableFrom(Invoice $invoice): Carbon
    {
        return $invoice->due_date->copy()->addDays((int) $this->grace_days);
    }

    public function appliesTo(Invoice $invoice): bool
    {
        return $this->is_active
            && $invoice->balance > 0
            && (float) $invoice->balance >= (float) $this->applies_above_amount;
    }

    public function describe(): string
    {
        $suffix = $this->grace_days > 0 ? " after {$this->grace_days} days grace" : '';

        return match ($this->method) {
            'flat' => number_format((float) $this->rate, 2).' flat'.$suffix,
            'percent_per_month' => rtrim(rtrim((string) $this->rate, '0'), '.').'% per month'.$suffix,
            'percent_per_annum' => rtrim(rtrim((string) $this->rate, '0'), '.').'% per annum'.$suffix,
            'slab' => 'Slab based'.$suffix,
            default => $this->method,
        };
    }
}
