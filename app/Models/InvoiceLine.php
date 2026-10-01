<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One charge on a bill.
 *
 * Not society-scoped: lines are only ever reached through their invoice, which
 * is itself scoped, and adding a second scope here would force a join on
 * every eager load.
 */
class InvoiceLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'meta' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function chargeHead(): BelongsTo
    {
        return $this->belongsTo(ChargeHead::class);
    }

    /** Fills amount, tax and line total from quantity, rate and tax rate. */
    public function computeTotals(): static
    {
        $this->amount = round((float) $this->quantity * (float) $this->rate, 2);
        $this->tax_amount = round((float) $this->amount * (float) $this->tax_rate / 100, 2);
        $this->line_total = round((float) $this->amount + (float) $this->tax_amount, 2);

        return $this;
    }

    public function isLateFee(): bool
    {
        return $this->source === 'late_fee';
    }
}
