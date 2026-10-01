<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gate extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function visitorLogs(): HasMany
    {
        return $this->hasMany(VisitorLog::class, 'gate_id');
    }
}
