<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\Billing;
use App\Services\Billing\Cashfree;
use App\Services\Billing\Razorpay;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class SubscriptionController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index()
    {
        $workspace = $this->current->get();

        return view('subscription.index', [
            'workspace' => $workspace,
            'plans' => config('billing.plans'),
            'cycles' => config('billing.cycles'),
            'usage' => collect(config('billing.limit_labels'))->map(fn ($label, $key) => [
                'label' => $label, 'used' => $workspace->usage($key), 'limit' => $workspace->limit($key),
            ]),
            'gateways' => self::gateways(),
            'payments' => Payment::with('invoice')->latest('id')->limit(50)->get(),
        ]);
    }

    /** Gateways that can take money right now. */
    public static function gateways(): array
    {
        return array_keys(array_filter([
            'razorpay' => Razorpay::enabled(),
            'cashfree' => Cashfree::enabled(),
            'test' => app()->environment('local', 'testing') && config('billing.test_payments'),
        ]));
    }

    public function billing(Request $request)
    {
        $data = $request->validate([
            'billing_name' => ['required', 'string', 'max:255'],
            'billing_gstin' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'billing_state' => ['required', Rule::in(config('billing.states'))],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'regex:/^\+?\d{10,15}$/'],
        ], ['billing_gstin.regex' => 'This GSTIN does not look right (15 characters, like 08ABCDE1234F1Z5).']);
        $data['billing_gstin'] = isset($data['billing_gstin']) ? strtoupper($data['billing_gstin']) : null;
        $this->current->get()->update($data);

        return back()->with('status', 'Billing details saved. They will appear on your next invoice.');
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('billing.plans')))],
            'cycle' => ['required', Rule::in(array_keys(config('billing.cycles')))],
            'gateway' => ['required', Rule::in(self::gateways())],
        ], ['gateway.in' => 'Online payment is not set up yet. Please contact support.', 'gateway.required' => 'Online payment is not set up yet. Please contact support.']);
        $workspace = $this->current->get();
        if (! $workspace->billing_state) {
            return back()->withErrors(['billing_state' => 'Please fill your billing details first (needed for the GST invoice).']);
        }
        $payment = Billing::newOrder($workspace, $request->user(), $data['plan'], $data['cycle'], $data['gateway']);

        if ($data['gateway'] === 'test') {
            Billing::markPaid($payment, 'test_'.$payment->id);

            return redirect()->route('subscription.index')->with('status', 'Test payment done. Your '.$payment->planName().' plan is active.');
        }

        try {
            if ($data['gateway'] === 'razorpay') {
                return view('subscription.pay', [
                    'payment' => $payment, 'gateway' => 'razorpay', 'workspace' => $workspace,
                    'razorpayOrderId' => app(Razorpay::class)->createOrder($payment),
                ]);
            }

            $phone = preg_replace('/\D+/', '', (string) $workspace->billing_phone);
            if (strlen($phone) < 10) {
                return back()->withErrors(['billing_phone' => 'Add a phone number in billing details to pay with Cashfree.']);
            }

            return view('subscription.pay', [
                'payment' => $payment, 'gateway' => 'cashfree', 'workspace' => $workspace,
                'sessionId' => app(Cashfree::class)->createOrder($payment, $workspace->billing_email ?: $request->user()->email, substr($phone, -10),
                    route('subscription.cashfree.return', $payment)),
            ]);
        } catch (Throwable $e) {
            report($e);
            Billing::markFailed($payment, ['error' => $e->getMessage()]);

            return back()->withErrors(['plan' => 'Could not start the payment. Please try again in a minute.']);
        }
    }

    /** Browser callback after Razorpay checkout. */
    public function razorpayCallback(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);
        $orderId = $payment->meta['razorpay_order_id'] ?? null;
        if ($payment->gateway !== 'razorpay' || $orderId !== $data['razorpay_order_id']
            || ! Razorpay::validPaymentSignature($orderId, $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return redirect()->route('subscription.index')->withErrors(['plan' => 'We could not verify this payment. If money was taken, it will be confirmed automatically in a few minutes.']);
        }
        Billing::markPaid($payment, $data['razorpay_payment_id']);

        return redirect()->route('subscription.index')->with('status', 'Payment received. Your '.$payment->planName().' plan is active.');
    }

    /** Cashfree sends the customer back here; we ask Cashfree whether the order is paid. */
    public function cashfreeReturn(Payment $payment)
    {
        if ($payment->gateway !== 'cashfree') {
            abort(404);
        }
        $status = $payment->status === 'paid' ? 'PAID' : app(Cashfree::class)->orderStatus($payment->order_id);
        if ($status === 'PAID') {
            Billing::markPaid($payment);

            return redirect()->route('subscription.index')->with('status', 'Payment received. Your '.$payment->planName().' plan is active.');
        }

        return redirect()->route('subscription.index')->withErrors(['plan' => 'The payment was not completed. You have not been charged; please try again.']);
    }

    public function invoice(Invoice $invoice)
    {
        return view('subscription.invoice', ['invoice' => $invoice]);
    }
}
