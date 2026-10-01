<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Committee extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'term_start' => 'date',
            'term_end' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(CommitteeMember::class, 'committee_id');
    }
}
