<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Gateway credentials for one society.
 *
 * Credentials are encrypted at rest. A society collects offline until a row
 * here is marked active, which is what flips online payment on.
 */
class PaymentGateway extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['credentials', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'pass_fee_to_payer' => 'boolean',
            'convenience_fee_percent' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function credential(string $key): ?string
    {
        return data_get($this->credentials ?? [], $key);
    }

    public function isLive(): bool
    {
        return $this->environment === 'live';
    }

    /** True once the keys needed to actually charge a card are present. */
    public function isConfigured(): bool
    {
        return filled($this->credential('key_id')) && filled($this->credential('key_secret'));
    }

    public function providerLabel(): string
    {
        return match ($this->provider) {
            'razorpay' => 'Razorpay',
            'cashfree' => 'Cashfree',
            'stripe' => 'Stripe',
            'payu' => 'PayU',
            default => ucfirst($this->provider),
        };
    }
}
