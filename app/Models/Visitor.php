<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_frequent' => 'boolean',
            'is_blacklisted' => 'boolean',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VisitorLog::class, 'visitor_id');
    }
}
