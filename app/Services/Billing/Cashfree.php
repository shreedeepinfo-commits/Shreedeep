<?php

namespace App\Services\Billing;

use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Cashfree Payment Gateway (PG API 2023-08-01): create an order, open the JS checkout, confirm by fetching the order. */
class Cashfree
{
    public static function enabled(): bool
    {
        return filled(config('billing.cashfree.app_id')) && filled(config('billing.cashfree.secret_key'));
    }

    public static function mode(): string
    {
        return config('billing.cashfree.sandbox') ? 'sandbox' : 'production';
    }

    private function http(): PendingRequest
    {
        $base = config('billing.cashfree.sandbox') ? 'https://sandbox.cashfree.com/pg' : 'https://api.cashfree.com/pg';

        return Http::baseUrl($base)->acceptJson()->timeout(20)->withHeaders([
            'x-client-id' => config('billing.cashfree.app_id'),
            'x-client-secret' => config('billing.cashfree.secret_key'),
            'x-api-version' => config('billing.cashfree.api_version'),
        ]);
    }

    /** @return string payment_session_id for the browser checkout */
    public function createOrder(Payment $payment, string $email, string $phone, string $returnUrl): string
    {
        $res = $this->http()->post('/orders', [
            'order_id' => $payment->order_id,
            'order_amount' => (float) $payment->total,
            'order_currency' => config('billing.currency'),
            'customer_details' => [
                'customer_id' => 'ws_'.$payment->workspace_id,
                'customer_email' => $email,
                'customer_phone' => $phone,
            ],
            'order_meta' => ['return_url' => $returnUrl],
            'order_note' => $payment->planName(),
        ]);
        if (! $res->successful() || ! $res->json('payment_session_id')) {
            throw new RuntimeException($res->json('message') ?? 'Could not start Cashfree payment.');
        }

        return $res->json('payment_session_id');
    }

    /** PAID, ACTIVE (not paid yet), EXPIRED ... */
    public function orderStatus(string $orderId): ?string
    {
        $res = $this->http()->get('/orders/'.rawurlencode($orderId));

        return $res->successful() ? $res->json('order_status') : null;
    }

    public static function validWebhookSignature(string $body, ?string $timestamp, ?string $signature): bool
    {
        $secret = config('billing.cashfree.secret_key');
        if (! $secret || ! $timestamp || ! $signature) {
            return false;
        }

        return hash_equals(base64_encode(hash_hmac('sha256', $timestamp.$body, $secret, true)), $signature);
    }
}
