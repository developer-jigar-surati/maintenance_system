<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
            'warranty_expires_on' => 'date',
            'depreciation_rate' => 'decimal:2',
        ];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function amcContracts(): HasMany
    {
        return $this->hasMany(AmcContract::class, 'asset_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'asset_id');
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class, 'asset_id');
    }
}
