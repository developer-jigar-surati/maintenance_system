<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'budgeted_amount' => 'decimal:2',
        ];
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class, 'financial_year_id');
    }

    public function chargeHead(): BelongsTo
    {
        return $this->belongsTo(ChargeHead::class, 'charge_head_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
