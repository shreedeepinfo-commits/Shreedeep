@extends('layouts.app', ['title' => 'Complete payment'])

@section('content')
<div class="max-w-lg mx-auto bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center mt-6">
    <span class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-600 grid place-items-center"><x-icon name="card" class="w-7 h-7" /></span>
    <div class="font-bold text-xl mt-4">{{ $payment->planName() }}</div>
    <div class="text-sm text-slate-500 mt-1">₹{{ number_format($payment->amount, 2) }} + GST ₹{{ number_format($payment->tax, 2) }}</div>
    <div class="text-3xl font-bold text-slate-900 mt-3">₹{{ number_format($payment->total, 2) }}</div>
    <button id="pay" class="mt-6 w-full rounded-xl px-4 py-3 text-sm font-semibold bg-brand-600 hover:bg-brand-700 text-white shadow-sm shadow-brand-600/30">Pay now</button>
    <a href="{{ route('subscription.index') }}" class="block mt-3 text-sm text-slate-500 hover:text-brand-700">Cancel</a>
    <p class="text-xs text-slate-400 mt-4">Secure payment by {{ $gateway === 'razorpay' ? 'Razorpay' : 'Cashfree' }}. UPI, cards, net banking and wallets accepted.</p>
</div>

@if ($gateway === 'razorpay')
    <form id="done" method="POST" action="{{ route('subscription.razorpay.callback', $payment) }}">
        @csrf
        <input type="hidden" name="razorpay_payment_id"><input type="hidden" name="razorpay_order_id"><input type="hidden" name="razorpay_signature">
    </form>
@endif
@endsection

@push('scripts')
@if ($gateway === 'razorpay')
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        const rzp = new Razorpay({
            key: @json(config('billing.razorpay.key_id')),
            order_id: @json($razorpayOrderId),
            amount: @json((int) round($payment->total * 100)),
            currency: 'INR',
            name: @json(config('billing.seller.name')),
            description: @json($payment->planName().' plan'),
            prefill: { name: @json($workspace->billing_name ?: $workspace->name), email: @json($workspace->billing_email ?: auth()->user()->email), contact: @json($workspace->billing_phone) },
            theme: { color: '#0B4E99' },
            handler: r => {
                const f = document.getElementById('done');
                ['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature'].forEach(k => f.elements[k].value = r[k]);
                f.submit();
            },
        });
        document.getElementById('pay').addEventListener('click', () => rzp.open());
        rzp.open();
    </script>
@else
    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
    <script>
        const cashfree = Cashfree({ mode: @json(\App\Services\Billing\Cashfree::mode()) });
        const go = () => cashfree.checkout({ paymentSessionId: @json($sessionId), redirectTarget: '_self' });
        document.getElementById('pay').addEventListener('click', go);
        go();
    </script>
@endif
@endpush
