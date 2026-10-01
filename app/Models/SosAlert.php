<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosAlert extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
