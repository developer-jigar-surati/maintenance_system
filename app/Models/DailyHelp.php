<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Domestic help engaged by individual units -- maids, cooks, drivers.
 * Carries a QR token so the gate can log entry without keying in details.
 */
class DailyHelp extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'police_verified' => 'boolean',
            'police_verified_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $help) => $help->qr_token ??= Str::random(32));
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'daily_help_unit')
            ->withPivot(['expected_from', 'expected_to', 'is_active'])
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(DailyHelpAttendance::class);
    }

    public function isInside(): bool
    {
        return $this->attendances()
            ->whereDate('attendance_date', today())
            ->whereNotNull('entered_at')
            ->whereNull('exited_at')
            ->exists();
    }

    public function typeLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->type));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
