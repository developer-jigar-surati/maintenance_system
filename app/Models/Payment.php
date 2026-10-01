<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Money received from a unit, however it arrived.
 *
 * Offline payments are keyed in by a committee member and wait for approval;
 * online ones are confirmed by the gateway. Either way, once the payment is
 * complete it is allocated to invoices and a receipt is issued.
 */
class Payment extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'unallocated_amount' => 'decimal:2',
            'gateway_fee' => 'decimal:2',
            'paid_at' => 'datetime',
            'instrument_date' => 'date',
            'approved_at' => 'datetime',
            'gateway_response' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $payment) => $payment->uuid ??= (string) Str::uuid());
    }

    // Relationships -------------------------------------------------------

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    // Predicates ----------------------------------------------------------

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function needsApproval(): bool
    {
        return $this->status === 'awaiting_approval';
    }

    public function isOnline(): bool
    {
        return $this->mode === 'online';
    }

    /** Amount already applied to invoices. */
    public function allocatedAmount(): float
    {
        return round((float) $this->allocations()->sum('amount'), 2);
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'neft' => 'NEFT',
            'rtgs' => 'RTGS',
            'imps' => 'IMPS',
            'upi' => 'UPI',
            'demand_draft' => 'Demand Draft',
            default => ucwords(str_replace('_', ' ', $this->method)),
        };
    }

    // Scopes --------------------------------------------------------------

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', 'awaiting_approval');
    }

    public function scopeWithCredit(Builder $query): Builder
    {
        return $query->completed()->where('unallocated_amount', '>', 0);
    }
}
