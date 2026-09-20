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
 * Someone employed by the society, or supplied to it by a vendor.
 */
class Staff extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $table = 'staff';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'left_on' => 'date',
            'monthly_salary' => 'decimal:2',
            'police_verified' => 'boolean',
            'police_verified_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function isOnDutyToday(): bool
    {
        return $this->attendances()
            ->whereDate('attendance_date', today())
            ->where('status', 'present')
            ->exists();
    }

    /** Days marked present in a month, used for the salary summary. */
    public function presentDays(int $year, int $month): int
    {
        return $this->attendances()
            ->whereYear('attendance_date', $year)
            ->whereMonth('attendance_date', $month)
            ->whereIn('status', ['present', 'half_day'])
            ->count();
    }

    public function departmentLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->department));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
