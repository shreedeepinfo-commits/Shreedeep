@php
    $s = $invoice->seller; $b = $invoice->buyer;
    $rate = config('billing.gst_rate');
    $money = fn ($v) => '₹'.number_format((float) $v, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Poppins, system-ui, sans-serif; color: #0f172a; background: #f1f5f9; margin: 0; padding: 32px; font-size: 13px; }
        .sheet { max-width: 820px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .top { background: linear-gradient(90deg, #04254a, #0B4E99); color: #fff; padding: 28px 32px; display: flex; justify-content: space-between; align-items: flex-start; }
        .top h1 { margin: 0; font-size: 22px; letter-spacing: .5px; }
        .muted { color: #64748b; } .top .muted { color: #bfdbfe; }
        .body { padding: 28px 32px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .label { font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #64748b; margin-bottom: 4px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; text-align: left; font-size: 11px; text-transform: uppercase; color: #64748b; padding: 10px; }
        td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .r { text-align: right; }
        .totals td { border: 0; padding: 6px 10px; }
        .grand td { font-size: 16px; font-weight: 700; border-top: 2px solid #0B4E99; padding-top: 10px; }
        .actions { max-width: 820px; margin: 0 auto 16px; display: flex; justify-content: flex-end; gap: 8px; }
        .btn { background: #0B4E99; color: #fff; border: 0; border-radius: 10px; padding: 9px 16px; font: 600 13px Poppins, sans-serif; cursor: pointer; text-decoration: none; }
        .btn.o { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
        @media print { body { background: #fff; padding: 0; } .actions { display: none; } .sheet { box-shadow: none; border-radius: 0; } }
    </style>
</head>
<body>
<div class="actions">
    <a href="{{ route('subscription.index') }}" class="btn o">← Back</a>
    <button class="btn" onclick="window.print()">Download / Print PDF</button>
</div>
<div class="sheet">
    <div class="top">
        <div>
            <h1>TAX INVOICE</h1>
            <div class="muted" style="margin-top:4px">{{ $invoice->number }}</div>
        </div>
        <div style="text-align:right">
            <div style="font-weight:700;font-size:15px">{{ $s['name'] ?? '' }}</div>
            @if (! empty($s['address']))<div class="muted">{{ $s['address'] }}</div>@endif
            @if (! empty($s['state']))<div class="muted">{{ $s['state'] }}</div>@endif
            @if (! empty($s['gstin']))<div class="muted">GSTIN: {{ $s['gstin'] }}</div>@endif
            @if (! empty($s['email']))<div class="muted">{{ $s['email'] }}</div>@endif
        </div>
    </div>
    <div class="body">
        <div class="grid">
            <div>
                <div class="label">Billed to</div>
                <div style="font-weight:600">{{ $b['name'] ?? '' }}</div>
                @if (! empty($b['address']))<div>{{ $b['address'] }}</div>@endif
                @if (! empty($b['state']))<div>{{ $b['state'] }}</div>@endif
                @if (! empty($b['gstin']))<div>GSTIN: {{ $b['gstin'] }}</div>@endif
                @if (! empty($b['email']))<div class="muted">{{ $b['email'] }}</div>@endif
                @if (! empty($b['phone']))<div class="muted">{{ $b['phone'] }}</div>@endif
            </div>
            <div style="text-align:right">
                <div class="label">Invoice date</div><div>{{ $invoice->issued_on->format('d M Y') }}</div>
                <div class="label" style="margin-top:10px">Place of supply</div><div>{{ $b['state'] ?? '—' }}</div>
                <div class="label" style="margin-top:10px">Payment</div><div>Paid · {{ $invoice->payment?->gateway_payment_id }}</div>
            </div>
        </div>
        <table>
            <thead><tr><th>Description</th><th>SAC</th><th>Period</th><th class="r">Amount</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $invoice->description }}</td>
                    <td>{{ $s['sac'] ?? '' }}</td>
                    <td>{{ $invoice->period_from->format('d M Y') }} – {{ $invoice->period_to->format('d M Y') }}</td>
                    <td class="r">{{ $money($invoice->subtotal) }}</td>
                </tr>
            </tbody>
        </table>
        <table class="totals" style="width:320px;margin-left:auto;margin-top:12px">
            <tr><td>Taxable value</td><td class="r">{{ $money($invoice->subtotal) }}</td></tr>
            @if ((float) $invoice->igst > 0)
                <tr><td>IGST @ {{ $rate }}%</td><td class="r">{{ $money($invoice->igst) }}</td></tr>
            @else
                <tr><td>CGST @ {{ $rate / 2 }}%</td><td class="r">{{ $money($invoice->cgst) }}</td></tr>
                <tr><td>SGST @ {{ $rate / 2 }}%</td><td class="r">{{ $money($invoice->sgst) }}</td></tr>
            @endif
            <tr class="grand"><td>Total</td><td class="r">{{ $money($invoice->total) }}</td></tr>
        </table>
        <p class="muted" style="margin-top:28px;font-size:11px">This is a computer-generated invoice and does not need a signature. WhatsApp conversation charges are billed separately by Meta.</p>
    </div>
</div>
</body>
</html>
