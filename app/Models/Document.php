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
 * A file in the society's document vault, or a folder holding others.
 */
class Document extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_folder' => 'boolean',
            'is_archived' => 'boolean',
            'expires_on' => 'date',
            'file_size' => 'integer',
            'version' => 'integer',
            'visibility_meta' => 'array',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'folder_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'folder_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isVisibleTo(User $user): bool
    {
        return match ($this->visibility) {
            'all' => true,
            'owners' => $user->billableUnits()->exists(),
            'committee' => $user->hasManagementRole() || $user->hasRole(\App\Enums\Role::COMMITTEE_MEMBER),
            'admin_only' => $user->hasManagementRole(),
            'specific_units' => $user->units()
                ->whereIn('units.id', $this->visibility_meta['unit_ids'] ?? [])->exists(),
            default => false,
        };
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->file_size;

        foreach (['B', 'KB', 'MB', 'GB'] as $i => $unit) {
            if ($bytes < 1024 ** ($i + 1) || $unit === 'GB') {
                return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1).' '.$unit;
            }
        }

        return $bytes.' B';
    }

    public function categoryLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->category));
    }

    public function isExpiring(int $withinDays = 30): bool
    {
        return $this->expires_on !== null
            && $this->expires_on->isBetween(now(), now()->addDays($withinDays));
    }

    public function scopeFiles(Builder $query): Builder
    {
        return $query->where('is_folder', false)->where('is_archived', false);
    }
}
