<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An authorisation to move goods, vehicles or furniture past the gate.
 * Verified by scanning its QR token.
 */
class GatePass extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'approved_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $pass) => $pass->qr_token ??= Str::random(48));
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** A pass the guard may act on right now. */
    public function isUsable(): bool
    {
        return $this->status === 'approved'
            && $this->valid_from->lte(now())
            && $this->valid_to->gte(now());
    }

    public function isExpired(): bool
    {
        return $this->valid_to->isPast() && $this->status !== 'used';
    }

    public function verificationUrl(): string
    {
        return route('gate-passes.verify', ['token' => $this->qr_token]);
    }

    public function typeLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->type));
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', 'approved')
            ->where('valid_from', '<=', now())
            ->where('valid_to', '>=', now());
    }
}
