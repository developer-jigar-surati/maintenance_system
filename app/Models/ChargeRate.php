<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one charge costs one slice of the society.
 *
 * Sits between the head's own rate and a per-unit override: a rate for a
 * building, or for a size of home. The calculator takes the most specific one
 * that applies, so a committee says "B wing pays 1,400" once instead of
 * putting an override on every flat in B wing.
 */
class ChargeRate extends Model
{
    use BelongsToSociety, HasFactory;

    protected $fillable = [
        'society_id', 'charge_head_id', 'scope', 'block_id',
        'configuration', 'rate', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function chargeHead(): BelongsTo
    {
        return $this->belongsTo(ChargeHead::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function scopeInForceOn(Builder $query, $on): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $on))
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on));
    }

    public function label(): string
    {
        return $this->scope === 'block'
            ? ($this->block?->name ?? 'A building')
            : (string) $this->configuration;
    }
}
