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
 * A circular or announcement, with read receipts so the committee can show a
 * notice actually reached people.
 */
class Notice extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'is_pinned' => 'boolean',
            'allow_comments' => 'boolean',
            'send_email' => 'boolean',
            'attachments' => 'array',
            'audience_meta' => 'array',
        ];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NoticeRead::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLive(): bool
    {
        return $this->status === 'published'
            && ($this->published_at === null || $this->published_at->isPast())
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }

    public function markReadBy(User $user): void
    {
        $this->reads()->firstOrCreate(
            ['user_id' => $user->id],
            ['society_id' => $this->society_id, 'read_at' => now()],
        );
    }

    public function readCount(): int
    {
        return $this->reads()->count();
    }

    /**
     * Whether a notice is addressed to this user, given their roles and the
     * units they live in.
     */
    public function isVisibleTo(User $user): bool
    {
        return match ($this->audience) {
            'all' => true,
            'owners' => $user->billableUnits()->exists(),
            'tenants' => $user->residencies()->where('relation', 'tenant')->where('status', 'active')->exists(),
            'committee' => $user->hasManagementRole() || $user->hasRole(\App\Enums\Role::COMMITTEE_MEMBER),
            'staff' => $user->hasAnyRole([\App\Enums\Role::STAFF, \App\Enums\Role::SECURITY_GUARD]),
            'specific_blocks' => $user->units()
                ->whereIn('block_id', $this->audience_meta['block_ids'] ?? [])->exists(),
            'specific_units' => $user->units()
                ->whereIn('units.id', $this->audience_meta['unit_ids'] ?? [])->exists(),
            default => false,
        };
    }

    public function categoryLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->category));
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
