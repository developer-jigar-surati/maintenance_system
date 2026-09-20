<?php

namespace App\Services\Payments\Gateways;

use App\Models\PaymentGateway;
use App\Models\Society;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the driver for a society's configured gateway.
 *
 * Registering a new provider means adding one class and one line here; nothing
 * in the billing or collection code changes.
 */
class GatewayManager
{
    /** @var array<string, class-string<PaymentGatewayDriver>> */
    protected array $drivers = [
        'razorpay' => RazorpayDriver::class,
    ];

    public function __construct(private Container $container) {}

    public function extend(string $key, string $driverClass): void
    {
        $this->drivers[$key] = $driverClass;
    }

    /** @return array<int, string> */
    public function available(): array
    {
        return array_keys($this->drivers);
    }

    public function supports(string $provider): bool
    {
        return isset($this->drivers[$provider]);
    }

    public function driver(string $provider): PaymentGatewayDriver
    {
        $class = $this->drivers[$provider]
            ?? throw new \InvalidArgumentException("No payment driver is registered for [{$provider}].");

        return $this->container->make($class);
    }

    public function for(PaymentGateway $gateway): PaymentGatewayDriver
    {
        return $this->driver($gateway->provider);
    }

    /**
     * The gateway a society should collect through, or null when it is running
     * offline-only. Callers must handle null rather than assuming online works.
     */
    public function activeFor(Society $society): ?PaymentGateway
    {
        if (! $society->acceptsOnlinePayments()) {
            return null;
        }

        $gateway = $society->activeGateway();

        return $gateway?->isConfigured() ? $gateway : null;
    }
}
