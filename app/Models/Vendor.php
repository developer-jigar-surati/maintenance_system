<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use BelongsToSociety, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tds_rate' => 'decimal:2',
            'rating' => 'integer',
            'contract_start' => 'date',
            'contract_end' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'vendor_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'vendor_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'vendor_id');
    }

    public function amcContracts(): HasMany
    {
        return $this->hasMany(AmcContract::class, 'vendor_id');
    }
}
