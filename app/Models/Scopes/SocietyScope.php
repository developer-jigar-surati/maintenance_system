<?php

namespace App\Models\Scopes;

use App\Support\SocietyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a society-owned model to the active society.
 *
 * Applied automatically by the BelongsToSociety trait. When no society is
 * active -- console commands, the super-admin console, tests that opt out --
 * the scope stands down and the query runs unfiltered.
 */
class SocietyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(SocietyContext::class);

        if (! $context->shouldScope()) {
            return;
        }

        $builder->where(
            $model->qualifyColumn('society_id'),
            $context->id()
        );
    }
}
