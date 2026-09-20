<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A vendor bill or other society spend, with an approval trail before payment.
 */
class Expense extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tds_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function chargeHead(): BelongsTo
    {
        return $this->belongsTo(ChargeHead::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Recomputes the payable total and how much of it is still outstanding.
     * TDS is withheld from the vendor, so it reduces what is actually paid out.
     */
    public function recalculate(bool $save = true): static
    {
        $this->total = round((float) $this->amount + (float) $this->tax_amount, 2);
        $this->amount_paid = round((float) $this->payments()->sum('amount'), 2);
        $this->balance = round((float) $this->total - (float) $this->tds_amount - (float) $this->amount_paid, 2);

        if (in_array($this->status, ['approved', 'partially_paid', 'paid'], true)) {
            $this->status = match (true) {
                $this->balance <= 0.004 => 'paid',
                $this->amount_paid > 0 => 'partially_paid',
                default => 'approved',
            };
        }

        if ($save) {
            $this->save();
        }

        return $this;
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'partially_paid', 'paid'], true);
    }

    public function isPayable(): bool
    {
        return $this->isApproved() && $this->balance > 0;
    }

    public function scopeAwaitingApproval(Builder $query): Builder
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', ['approved', 'partially_paid'])->where('balance', '>', 0);
    }
}
