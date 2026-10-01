<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One visit, from the resident's pre-approval through to the exit scan.
 */
class VisitorLog extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expected_at' => 'datetime',
            'expected_until' => 'datetime',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
            'approved_at' => 'datetime',
            'accompanying_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            // Short, quotable at the gate, and unambiguous over the phone.
            $log->pass_code ??= Str::upper(Str::random(6));
        });
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function preApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pre_approved_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isInside(): bool
    {
        return $this->status === 'inside';
    }

    public function isExpired(): bool
    {
        return in_array($this->status, ['expected', 'approved'], true)
            && $this->expected_until !== null
            && $this->expected_until->isPast();
    }

    public function durationMinutes(): ?int
    {
        return $this->entered_at && $this->exited_at
            ? (int) $this->entered_at->diffInMinutes($this->exited_at)
            : null;
    }

    public function purposeLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->purpose));
    }

    public function scopeInside(Builder $query): Builder
    {
        return $query->where('status', 'inside');
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }
}
