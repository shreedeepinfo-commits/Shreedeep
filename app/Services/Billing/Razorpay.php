<?php

namespace App\Services\Billing;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Razorpay Standard Checkout: create an order, open checkout.js, verify the signature. */
class Razorpay
{
    public static function enabled(): bool
    {
        return filled(config('billing.razorpay.key_id')) && filled(config('billing.razorpay.key_secret'));
    }

    /** @return string Razorpay order id */
    public function createOrder(Payment $payment): string
    {
        $res = Http::withBasicAuth(config('billing.razorpay.key_id'), config('billing.razorpay.key_secret'))
            ->acceptJson()->timeout(20)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => (int) round($payment->total * 100), // paise
                'currency' => config('billing.currency'),
                'receipt' => $payment->order_id,
                'notes' => ['workspace_id' => (string) $payment->workspace_id, 'plan' => $payment->plan, 'cycle' => $payment->cycle],
            ]);
        if (! $res->successful() || ! $res->json('id')) {
            throw new RuntimeException($res->json('error.description') ?? 'Could not start Razorpay payment.');
        }
        $payment->update(['meta' => array_merge($payment->meta ?? [], ['razorpay_order_id' => $res->json('id')])]);

        return $res->json('id');
    }

    /** Signature sent to the browser after a successful checkout. */
    public static function validPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, (string) config('billing.razorpay.key_secret')), $signature);
    }

    public static function validWebhookSignature(string $body, ?string $signature): bool
    {
        $secret = config('billing.razorpay.webhook_secret');

        return $secret && $signature && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }
}
