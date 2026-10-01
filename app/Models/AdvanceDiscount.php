<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One building's deal for paying the year up front.
 *
 * The plan holds what the society offers everybody. A row here exists only
 * where a building was promised something different, which happens whenever
 * one wing has been subsidising another and the meeting settles it with a
 * better prepayment rate rather than a lower monthly amount.
 */
class AdvanceDiscount extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
        ];
    }

    public function billingPlan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}
