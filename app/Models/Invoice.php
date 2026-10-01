<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A bill raised against a unit for one billing period.
 *
 * Totals are derived from the lines rather than set directly, so a late-fee
 * posting or a manual line can never leave the header inconsistent.
 */
class Invoice extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'late_fee_total' => 'decimal:2',
            'arrears_amount' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_accrued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $invoice) => $invoice->uuid ??= (string) Str::uuid());
    }

    // Relationships -------------------------------------------------------

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function billingPlan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')
            ->withPivot('amount')
            ->withTimestamps();
    }

    // Totals --------------------------------------------------------------

    /**
     * Recomputes every monetary field from the invoice lines and the payments
     * applied to it, then persists. Call after any line or allocation change.
     */
    public function recalculate(bool $save = true): static
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        $this->subtotal = round((float) $lines->sum('amount'), 2);
        $this->tax_total = round((float) $lines->sum('tax_amount'), 2);
        $this->late_fee_total = round(
            (float) $lines->where('source', 'late_fee')->sum('line_total'), 2
        );

        $this->total = round((float) $lines->sum('line_total') - (float) $this->discount_total, 2);

        $this->amount_paid = round((float) $this->allocations()->sum('amount'), 2);
        $this->balance = round((float) $this->total - (float) $this->amount_paid, 2);

        $this->syncStatus();

        if ($save) {
            $this->save();
        }

        return $this;
    }

    /**
     * Moves the invoice to the status its numbers imply. Terminal states
     * (draft, cancelled, written off) are left alone.
     */
    public function syncStatus(): void
    {
        if (in_array($this->status, ['draft', 'cancelled', 'written_off'], true)) {
            return;
        }

        $this->status = match (true) {
            $this->balance <= 0.004 => 'paid',
            $this->amount_paid > 0 => 'partially_paid',
            $this->due_date !== null && $this->due_date->endOfDay()->isPast() => 'overdue',
            default => 'issued',
        };
    }

    // Predicates ----------------------------------------------------------

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['issued', 'partially_paid', 'overdue'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->due_date !== null
            && $this->due_date->endOfDay()->isPast();
    }

    public function daysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->due_date->endOfDay()->diffInDays(now());
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    // Scopes --------------------------------------------------------------

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['issued', 'partially_paid', 'overdue']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['draft', 'cancelled']);
    }
}
