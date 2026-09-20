<?php

namespace App\Services\Payments\Gateways;

use App\Models\PaymentGateway;
use App\Models\Unit;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

/**
 * Razorpay implementation, the default for Indian societies.
 *
 * Credentials come from the society's own payment_gateways row, never from
 * global config, so each society collects into its own bank account.
 */
class RazorpayDriver implements PaymentGatewayDriver
{
    public function key(): string
    {
        return 'razorpay';
    }

    public function createOrder(PaymentGateway $gateway, Unit $unit, float $amount, array $context = []): array
    {
        $api = $this->api($gateway);

        // Razorpay works in the smallest currency unit, so rupees become paise.
        $order = $api->order->create([
            'amount' => (int) round($amount * 100),
            'currency' => $unit->society->currency ?: 'INR',
            'receipt' => $context['receipt'] ?? ('unit-'.$unit->id.'-'.now()->timestamp),
            'notes' => array_filter([
                'society' => $unit->society->name,
                'society_id' => (string) $unit->society_id,
                'unit' => $unit->unit_number,
                'unit_id' => (string) $unit->id,
                'invoice_ids' => isset($context['invoice_ids'])
                    ? implode(',', (array) $context['invoice_ids'])
                    : null,
            ]),
        ]);

        return [
            'provider' => $this->key(),
            'order_id' => $order['id'],
            'amount' => $amount,
            'amount_minor' => $order['amount'],
            'currency' => $order['currency'],
            'key_id' => $gateway->credential('key_id'),
            'name' => $unit->society->name,
            'description' => $context['description'] ?? 'Maintenance payment',
            'prefill' => array_filter([
                'name' => $context['payer_name'] ?? null,
                'email' => $context['payer_email'] ?? null,
                'contact' => $context['payer_phone'] ?? null,
            ]),
        ];
    }

    public function verify(PaymentGateway $gateway, array $payload): array
    {
        $orderId = $payload['razorpay_order_id'] ?? null;
        $paymentId = $payload['razorpay_payment_id'] ?? null;
        $signature = $payload['razorpay_signature'] ?? null;

        $failed = [
            'verified' => false,
            'gateway_payment_id' => $paymentId,
            'gateway_order_id' => $orderId,
            'amount' => null,
            'fee' => 0.0,
            'raw' => $payload,
        ];

        if (blank($orderId) || blank($paymentId) || blank($signature)) {
            return $failed;
        }

        $api = $this->api($gateway);

        try {
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);
        } catch (SignatureVerificationError $e) {
            Log::warning('Razorpay signature verification failed', [
                'society_id' => $gateway->society_id,
                'order_id' => $orderId,
            ]);

            return $failed;
        }

        // Re-read the payment from Razorpay rather than trusting the browser
        // for the amount or the captured state.
        $remote = $api->payment->fetch($paymentId);

        if (! in_array($remote['status'], ['captured', 'authorized'], true)) {
            return $failed;
        }

        return [
            'verified' => true,
            'gateway_payment_id' => $paymentId,
            'gateway_order_id' => $orderId,
            'amount' => round(((int) $remote['amount']) / 100, 2),
            'fee' => round(((int) ($remote['fee'] ?? 0)) / 100, 2),
            'method' => $remote['method'] ?? null,
            'raw' => $remote->toArray(),
        ];
    }

    public function verifyWebhook(PaymentGateway $gateway, string $rawBody, string $signature): bool
    {
        $secret = $gateway->webhook_secret;

        if (blank($secret)) {
            return false;
        }

        try {
            $this->api($gateway)->utility->verifyWebhookSignature($rawBody, $signature, $secret);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }

    public function testConnection(PaymentGateway $gateway): bool
    {
        try {
            // A trivial authenticated read; throws when the keys are wrong.
            $this->api($gateway)->order->all(['count' => 1]);

            return true;
        } catch (\Throwable $e) {
            Log::info('Razorpay connection test failed', [
                'society_id' => $gateway->society_id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function api(PaymentGateway $gateway): Api
    {
        if (! $gateway->isConfigured()) {
            throw new \DomainException('Razorpay keys are not configured for this society.');
        }

        return new Api($gateway->credential('key_id'), $gateway->credential('key_secret'));
    }
}
