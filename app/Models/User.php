<?php

namespace App\Models;

use App\Enums\Role as RoleName;
use App\Support\SocietyContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * A person who can sign in.
 *
 * Users are platform-level: one account can be an owner in one society and a
 * tenant in another. Which society they are acting in is held in
 * current_society_id and mirrored into SocietyContext for the request.
 */
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    // Relationships -------------------------------------------------------

    public function societies(): BelongsToMany
    {
        return $this->belongsToMany(Society::class)
            ->using(SocietyUser::class)
            ->withPivot(['status', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function currentSociety(): BelongsTo
    {
        return $this->belongsTo(Society::class, 'current_society_id');
    }

    public function residencies(): HasMany
    {
        return $this->hasMany(UnitResident::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'raised_by');
    }

    public function staffRecord(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    // Behaviour -----------------------------------------------------------

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /** Societies this user is an active member of. */
    public function activeSocieties()
    {
        return $this->societies()->wherePivot('status', 'active');
    }

    public function belongsToSociety(Society|int $society): bool
    {
        $id = $society instanceof Society ? $society->id : $society;

        return $this->societies()->where('societies.id', $id)->exists();
    }

    /**
     * Units this user is attached to in the active society, whatever the
     * relation -- owner, tenant or family member.
     */
    public function units()
    {
        return Unit::query()
            ->whereHas('residents', fn ($q) => $q->where('user_id', $this->id)->where('status', 'active'));
    }

    /** Units the user is billed for, i.e. those they own or rent directly. */
    public function billableUnits()
    {
        return Unit::query()
            ->whereHas('residents', fn ($q) => $q
                ->where('user_id', $this->id)
                ->where('status', 'active')
                ->whereIn('relation', ['owner', 'co_owner', 'tenant']));
    }

    /**
     * Roles in spatie are society-scoped, so a permission check is only
     * meaningful once the team id is pointed at the right society.
     */
    public function hasManagementRole(): bool
    {
        return $this->isSuperAdmin() || $this->hasAnyRole(RoleName::MANAGEMENT);
    }

    public function isResidentOnly(): bool
    {
        return ! $this->isSuperAdmin() && ! $this->hasManagementRole();
    }

    public function switchTo(Society $society): void
    {
        $this->forceFill(['current_society_id' => $society->id])->save();
        app(SocietyContext::class)->set($society);
        setPermissionsTeamId($society->id);
        $this->unsetRelation('roles')->unsetRelation('permissions');
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
