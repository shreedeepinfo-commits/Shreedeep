<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\Billing;
use App\Services\Billing\Cashfree;
use App\Services\Billing\Razorpay;
use Illuminate\Http\Request;

/** Server-to-server payment confirmations, so a plan is activated even if the customer closes the browser. */
class PaymentWebhookController extends Controller
{
    public function razorpay(Request $request)
    {
        $body = $request->getContent();
        if (! Razorpay::validWebhookSignature($body, $request->header('X-Razorpay-Signature'))) {
            return response('Invalid signature', 401);
        }

        $event = $request->json('event');
        $entity = $request->json('payload.payment.entity') ?? [];
        $orderId = $entity['order_id'] ?? $request->json('payload.order.entity.id');
        $payment = $orderId ? Payment::withoutGlobalScopes()->where('gateway', 'razorpay')->where('meta->razorpay_order_id', $orderId)->first() : null;
        if (! $payment) {
            return response('ok');
        }

        if (in_array($event, ['payment.captured', 'order.paid'], true)) {
            $paise = (int) ($entity['amount'] ?? $request->json('payload.order.entity.amount_paid') ?? 0);
            if ($paise !== (int) round($payment->total * 100)) {
                return response('Amount mismatch', 422);
            }
            Billing::markPaid($payment, $entity['id'] ?? null);
        } elseif ($event === 'payment.failed') {
            Billing::markFailed($payment, ['error' => $entity['error_description'] ?? 'Payment failed']);
        }

        return response('ok');
    }

    public function cashfree(Request $request)
    {
        $body = $request->getContent();
        if (! Cashfree::validWebhookSignature($body, $request->header('x-webhook-timestamp'), $request->header('x-webhook-signature'))) {
            return response('Invalid signature', 401);
        }

        $payment = Payment::withoutGlobalScopes()->where('gateway', 'cashfree')
            ->where('order_id', (string) $request->json('data.order.order_id'))->first();
        if (! $payment) {
            return response('ok');
        }

        $status = $request->json('data.payment.payment_status');
        if ($status === 'SUCCESS') {
            if (abs((float) $request->json('data.payment.payment_amount') - (float) $payment->total) > 0.01) {
                return response('Amount mismatch', 422);
            }
            Billing::markPaid($payment, (string) $request->json('data.payment.cf_payment_id'));
        } elseif (in_array($status, ['FAILED', 'USER_DROPPED'], true)) {
            Billing::markFailed($payment, ['error' => $request->json('data.error_details.error_description') ?? $status]);
        }

        return response('ok');
    }
}
