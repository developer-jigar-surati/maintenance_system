<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Block extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'floor_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'block_id');
    }

    public function parkingSlots(): HasMany
    {
        return $this->hasMany(ParkingSlot::class, 'block_id');
    }
}
