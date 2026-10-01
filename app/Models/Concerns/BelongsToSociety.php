<?php

namespace App\Models\Concerns;

use App\Models\Scopes\SocietyScope;
use App\Models\Society;
use App\Support\SocietyContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by a society.
 *
 * Adds the global scope that filters reads, and stamps society_id on write so
 * callers never have to remember it. A model that already carries an explicit
 * society_id keeps it, which is what makes cross-society seeding possible.
 */
trait BelongsToSociety
{
    public static function bootBelongsToSociety(): void
    {
        static::addGlobalScope(new SocietyScope);

        static::creating(function ($model) {
            if ($model->society_id === null) {
                $model->society_id = app(SocietyContext::class)->id();
            }
        });
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    /**
     * The owning society, whether or not the relation was eager loaded.
     *
     * Services reach for this rather than `$model->society`, because with lazy
     * loading disabled a plain property read throws on an unloaded relation.
     */
    public function resolveSociety(): Society
    {
        if (! $this->relationLoaded('society')) {
            $this->setRelation('society', Society::withoutGlobalScopes()->findOrFail($this->society_id));
        }

        return $this->getRelation('society');
    }

    /** Escape hatch for reporting that deliberately spans societies. */
    public function scopeAcrossSocieties($query)
    {
        return $query->withoutGlobalScope(SocietyScope::class);
    }

    public function scopeForSociety($query, Society|int $society)
    {
        return $query->withoutGlobalScope(SocietyScope::class)
            ->where($this->qualifyColumn('society_id'), $society instanceof Society ? $society->id : $society);
    }
}
