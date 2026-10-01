<?php

namespace App\Services\Payments\Gateways;

use App\Models\PaymentGateway;
use App\Models\Unit;

/**
 * Contract every online payment provider implements.
 *
 * Keeping this narrow is what lets a society switch from Razorpay to Cashfree
 * without the billing code noticing: the rest of the system only ever asks for
 * an order, then verifies what comes back.
 */
interface PaymentGatewayDriver
{
    /** Provider key, matching the payment_gateways.provider column. */
    public function key(): string;

    /**
     * Opens an order with the provider and returns whatever the checkout
     * widget needs. The shape is provider-specific and passed through to the
     * front end untouched.
     *
     * @param  array<string, mixed>  $context  invoice ids, payer details, notes
     * @return array<string, mixed>
     */
    public function createOrder(PaymentGateway $gateway, Unit $unit, float $amount, array $context = []): array;

    /**
     * Confirms that a callback genuinely came from the provider and that the
     * payment succeeded. Must never trust amounts supplied by the browser.
     *
     * @param  array<string, mixed>  $payload
     * @return array{verified: bool, gateway_payment_id: ?string, gateway_order_id: ?string, amount: ?float, fee: float, raw: array<string, mixed>}
     */
    public function verify(PaymentGateway $gateway, array $payload): array;

    /**
     * Validates a webhook signature against the society's webhook secret.
     */
    public function verifyWebhook(PaymentGateway $gateway, string $rawBody, string $signature): bool;

    /** Confirms the configured credentials actually work. */
    public function testConnection(PaymentGateway $gateway): bool;
}
