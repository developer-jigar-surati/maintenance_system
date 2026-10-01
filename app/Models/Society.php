<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A managed community: apartment complex, villa project, gated township,
 * commercial park, and so on. This is the tenant boundary -- every other
 * record in the system belongs to exactly one society.
 */
class Society extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'registered_on' => 'date',
            'onboarded_at' => 'datetime',
            'settings' => 'array',
            'gst_enabled' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'financial_year_start_month' => 'integer',
            'planned_unit_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $society) {
            $society->uuid ??= (string) Str::uuid();
            $society->slug ??= static::uniqueSlug($society->name);
            $society->code ??= static::uniqueCode($society->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'society';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /** Short human-facing identifier, used as a prefix on document numbers. */
    public static function uniqueCode(string $name): string
    {
        $base = Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', $name) ?: 'SOC', 0, 4));
        $code = $base;
        $i = 2;

        while (static::withTrashed()->where('code', $code)->exists()) {
            $code = $base.$i;
            $i++;
        }

        return $code;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Relationships -------------------------------------------------------

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(SocietyUser::class)
            ->withPivot(['status', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /*
     * Every relation below drops the child's society global scope.
     *
     * The parent already constrains these rows by society_id, so the scope
     * adds nothing when reading the active society -- and is actively wrong
     * when reading any other one, because it narrows the query to whichever
     * society the request happens to be acting in. That is how a platform
     * console ends up reporting zero units for every society but the current
     * one, and how currentFinancialYear() returns null for the rest.
     */

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->withoutGlobalScopes();
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->withoutGlobalScopes();
    }

    public function financialYears(): HasMany
    {
        return $this->hasMany(FinancialYear::class)->withoutGlobalScopes();
    }

    public function paymentGateways(): HasMany
    {
        return $this->hasMany(PaymentGateway::class)->withoutGlobalScopes();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->withoutGlobalScopes();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->withoutGlobalScopes();
    }

    // Behaviour -----------------------------------------------------------

    /**
     * Whether residents can pay through a gateway right now. Requires both the
     * society's chosen mode and a gateway that has actually been activated, so
     * a half-finished setup never shows a dead "Pay online" button.
     */
    public function acceptsOnlinePayments(): bool
    {
        return in_array($this->payment_mode, ['online', 'both'], true)
            && $this->paymentGateways()->where('is_active', true)->exists();
    }

    public function acceptsOfflinePayments(): bool
    {
        return in_array($this->payment_mode, ['offline', 'both'], true);
    }

    public function activeGateway(): ?PaymentGateway
    {
        return $this->paymentGateways()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();
    }

    public function currentFinancialYear(): ?FinancialYear
    {
        return $this->financialYears()->where('is_current', true)->first();
    }

    /** Reads a dotted key out of the settings JSON column. */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function putSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
    }

    public function isOnboarding(): bool
    {
        return $this->status === 'onboarding';
    }

    public function typeLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->type));
    }

    /** The area field per-sqft billing should read for this society. */
    public function areaUnitLabel(): string
    {
        return match ($this->area_unit) {
            'sqm' => 'sq.m.',
            'sqyd' => 'sq.yd.',
            default => 'sq.ft.',
        };
    }
}
