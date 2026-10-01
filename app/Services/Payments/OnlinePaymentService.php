<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Unit;
use App\Models\User;
use App\Services\NumberGenerator;
use App\Services\Payments\Gateways\GatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Drives an online collection from checkout through to receipt.
 *
 * A pending Payment row is created up front so an abandoned or failed checkout
 * leaves a trail, and only a verified callback moves it to completed.
 */
class OnlinePaymentService
{
    public function __construct(
        private GatewayManager $gateways,
        private NumberGenerator $numbers,
        private PaymentRecorder $recorder,
    ) {}

    /**
     * Opens a checkout for a unit.
     *
     * @param  array<int>  $invoiceIds  bills this payment is meant to settle
     * @return array{payment: Payment, checkout: array<string, mixed>}
     */
    public function startCheckout(Unit $unit, float $amount, array $invoiceIds = [], ?User $payer = null): array
    {
        $society = $unit->society;
        $gateway = $this->gateways->activeFor($society)
            ?? throw new \DomainException('This society is not set up to accept online payments.');

        if ($amount <= 0) {
            throw new \DomainException('Payment amount must be greater than zero.');
        }

        $driver = $this->gateways->for($gateway);

        return DB::transaction(function () use ($unit, $amount, $invoiceIds, $payer, $society, $gateway, $driver) {
            $payment = Payment::create([
                'society_id' => $society->id,
                'unit_id' => $unit->id,
                'payer_user_id' => $payer?->id ?? $unit->billingContact()?->user_id,
                'payment_number' => $this->numbers->next(NumberGenerator::PAYMENT, $society),
                'amount' => round($amount, 2),
                'unallocated_amount' => round($amount, 2),
                'paid_at' => now(),
                'method' => 'netbanking',
                'mode' => 'online',
                'status' => 'pending',
                'payment_gateway_id' => $gateway->id,
                'notes' => $invoiceIds ? 'Invoices: '.implode(', ', $invoiceIds) : null,
            ]);

            $checkout = $driver->createOrder($gateway, $unit, $amount, [
                'receipt' => $payment->payment_number,
                'invoice_ids' => $invoiceIds,
                'payer_name' => $payer?->name,
                'payer_email' => $payer?->email,
                'payer_phone' => $payer?->phone,
                'description' => 'Maintenance payment for '.$unit->label,
            ]);

            $payment->forceFill(['gateway_order_id' => $checkout['order_id'] ?? null])->save();

            return ['payment' => $payment, 'checkout' => $checkout];
        });
    }

    /**
     * Handles the browser callback. Verifies with the provider before trusting
     * anything, and refuses a callback whose amount does not match the order.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int>  $invoiceIds
     */
    public function confirm(Payment $payment, array $payload, array $invoiceIds = []): Receipt
    {
        if ($payment->isCompleted()) {
            return $payment->receipt ?? $this->recorder->complete($payment, $invoiceIds ?: null);
        }

        $gateway = $payment->gateway
            ?? throw new \DomainException('This payment is not linked to a gateway.');

        $result = $this->gateways->for($gateway)->verify($gateway, $payload);

        if (! $result['verified']) {
            $payment->forceFill([
                'status' => 'failed',
                'gateway_response' => $result['raw'] ?? $payload,
            ])->save();

            throw new \DomainException('Payment could not be verified with the gateway.');
        }

        // The gateway is the authority on how much was actually charged.
        if ($result['amount'] !== null && abs($result['amount'] - (float) $payment->amount) >= 0.01) {
            Log::warning('Gateway amount mismatch', [
                'payment_id' => $payment->id,
                'expected' => (float) $payment->amount,
                'actual' => $result['amount'],
            ]);

            $payment->forceFill([
                'status' => 'failed',
                'gateway_response' => $result['raw'],
            ])->save();

            throw new \DomainException('The amount confirmed by the gateway does not match this payment.');
        }

        $payment->forceFill([
            'gateway_payment_id' => $result['gateway_payment_id'],
            'gateway_order_id' => $result['gateway_order_id'] ?? $payment->gateway_order_id,
            'gateway_signature' => $payload['razorpay_signature'] ?? null,
            'gateway_response' => $result['raw'],
            'gateway_fee' => $result['fee'] ?? 0,
            'method' => $this->mapMethod($result['method'] ?? null),
            'paid_at' => now(),
        ])->save();

        return $this->recorder->complete($payment, $invoiceIds ?: null);
    }

    /**
     * Confirms from a webhook rather than a browser redirect, which is what
     * saves a payment when the resident closes the tab too early.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleWebhook(Payment $payment, array $event): ?Receipt
    {
        if ($payment->isCompleted()) {
            return $payment->receipt;
        }

        $entity = data_get($event, 'payload.payment.entity', []);

        if (! in_array(data_get($entity, 'status'), ['captured', 'authorized'], true)) {
            return null;
        }

        $amount = round(((int) data_get($entity, 'amount', 0)) / 100, 2);

        if (abs($amount - (float) $payment->amount) >= 0.01) {
            Log::warning('Webhook amount mismatch', ['payment_id' => $payment->id]);

            return null;
        }

        $payment->forceFill([
            'gateway_payment_id' => data_get($entity, 'id'),
            'gateway_response' => $entity,
            'gateway_fee' => round(((int) data_get($entity, 'fee', 0)) / 100, 2),
            'method' => $this->mapMethod(data_get($entity, 'method')),
            'paid_at' => now(),
        ])->save();

        return $this->recorder->complete($payment);
    }

    /** Translates provider method names onto our own payment methods. */
    private function mapMethod(?string $method): string
    {
        return match ($method) {
            'upi' => 'upi',
            'card' => 'card',
            'netbanking' => 'netbanking',
            'wallet' => 'wallet',
            'emi' => 'card',
            default => 'netbanking',
        };
    }
}
