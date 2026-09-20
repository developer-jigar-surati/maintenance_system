<?php

namespace Tests\Feature\Billing;

use App\Models\PaymentGateway;
use App\Services\Payments\Gateways\GatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Online collection must stay off until a gateway is genuinely configured and
 * activated, so a resident is never shown a checkout button that cannot work.
 */
class PaymentModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_society_collects_offline_only(): void
    {
        $society = $this->makeSociety(['payment_mode' => 'offline']);

        $this->assertTrue($society->acceptsOfflinePayments());
        $this->assertFalse($society->acceptsOnlinePayments());
        $this->assertNull(app(GatewayManager::class)->activeFor($society));
    }

    public function test_choosing_online_alone_does_not_enable_it(): void
    {
        $society = $this->makeSociety(['payment_mode' => 'both']);

        // The mode says online, but no gateway exists yet.
        $this->assertFalse($society->acceptsOnlinePayments());
    }

    public function test_an_inactive_gateway_does_not_enable_online(): void
    {
        $society = $this->makeSociety(['payment_mode' => 'both']);

        PaymentGateway::create([
            'society_id' => $society->id,
            'provider' => 'razorpay',
            'environment' => 'test',
            'credentials' => ['key_id' => 'rzp_test_x', 'key_secret' => 'secret'],
            'is_active' => false,
        ]);

        $this->assertFalse($society->fresh()->acceptsOnlinePayments());
    }

    public function test_an_active_configured_gateway_enables_online(): void
    {
        $society = $this->makeSociety(['payment_mode' => 'both']);

        PaymentGateway::create([
            'society_id' => $society->id,
            'provider' => 'razorpay',
            'environment' => 'test',
            'credentials' => ['key_id' => 'rzp_test_x', 'key_secret' => 'secret'],
            'is_active' => true,
            'is_default' => true,
        ]);

        $society = $society->fresh();

        $this->assertTrue($society->acceptsOnlinePayments());
        $this->assertNotNull(app(GatewayManager::class)->activeFor($society));
    }

    public function test_an_active_gateway_with_no_keys_is_not_usable(): void
    {
        $society = $this->makeSociety(['payment_mode' => 'both']);

        PaymentGateway::create([
            'society_id' => $society->id,
            'provider' => 'razorpay',
            'environment' => 'test',
            'credentials' => [],
            'is_active' => true,
        ]);

        // The society reports online as on, but the manager refuses to hand
        // back a gateway that cannot actually charge anything.
        $this->assertNull(app(GatewayManager::class)->activeFor($society->fresh()));
    }

    public function test_an_online_only_society_does_not_accept_offline(): void
    {
        $society = $this->makeSociety(['payment_mode' => 'online']);

        $this->assertFalse($society->acceptsOfflinePayments());
    }

    public function test_gateway_credentials_are_encrypted_at_rest(): void
    {
        $society = $this->makeSociety();

        $gateway = PaymentGateway::create([
            'society_id' => $society->id,
            'provider' => 'razorpay',
            'environment' => 'test',
            'credentials' => ['key_id' => 'rzp_test_secret_value', 'key_secret' => 'super-secret'],
            'is_active' => true,
        ]);

        $raw = DB::table('payment_gateways')
            ->where('id', $gateway->id)
            ->value('credentials');

        $this->assertStringNotContainsString('super-secret', $raw);
        $this->assertStringNotContainsString('rzp_test_secret_value', $raw);
        $this->assertSame('super-secret', $gateway->fresh()->credential('key_secret'));
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(GatewayManager::class)->driver('not-a-real-provider');
    }
}
