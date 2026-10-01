<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitChargeOverride extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_exempt' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function chargeHead(): BelongsTo
    {
        return $this->belongsTo(ChargeHead::class, 'charge_head_id');
    }

    /** Whoever agreed to this exception, named on the unit's own page. */
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
