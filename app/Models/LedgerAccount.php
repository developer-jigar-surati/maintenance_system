<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An account in the society's chart of accounts.
 */
class LedgerAccount extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** Assets and expenses increase on the debit side; the rest on credit. */
    public function normalSide(): string
    {
        return in_array($this->type, ['asset', 'expense'], true) ? 'debit' : 'credit';
    }

    /**
     * Balance as at a date, signed so a positive number always means "more of
     * what this account normally holds".
     */
    public function balanceAsOf(?string $date = null): float
    {
        $query = $this->lines()
            ->when($date, fn ($q) => $q->whereHas(
                'journalEntry',
                fn ($e) => $e->whereDate('entry_date', '<=', $date)->where('is_posted', true)
            ))
            ->when(! $date, fn ($q) => $q->whereHas('journalEntry', fn ($e) => $e->where('is_posted', true)));

        $debit = (float) (clone $query)->sum('debit');
        $credit = (float) (clone $query)->sum('credit');

        $opening = (float) $this->opening_balance
            * ($this->opening_balance_side === $this->normalSide() ? 1 : -1);

        $movement = $this->normalSide() === 'debit' ? $debit - $credit : $credit - $debit;

        return round($opening + $movement, 2);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
