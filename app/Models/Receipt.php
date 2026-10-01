<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The digital receipt issued against a completed payment -- the replacement
 * for a hand-written rasid. Numbered in its own gap-free per-society series.
 */
class Receipt extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'amount' => 'decimal:2',
            'emailed_at' => 'datetime',
            'is_cancelled' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $receipt) => $receipt->uuid ??= (string) Str::uuid());
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** Stable public URL for verifying a receipt, encoded into its QR code. */
    public function verificationUrl(): string
    {
        return route('receipts.verify', ['uuid' => $this->uuid]);
    }

    public function isValid(): bool
    {
        return ! $this->is_cancelled;
    }
}
