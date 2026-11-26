<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Plan prices, GST, turning a paid order into plan time, and GST invoices. */
class Billing
{
    /** Price before GST for a plan and billing cycle, in rupees. */
    public static function price(string $plan, string $cycle): float
    {
        $monthly = (int) config("billing.plans.{$plan}.price");
        $c = config("billing.cycles.{$cycle}");

        return round($monthly * $c['months'] * (100 - $c['discount']) / 100, 2);
    }

    public static function gst(float $amount): float
    {
        return round($amount * config('billing.gst_rate') / 100, 2);
    }

    /** Same state as the seller: CGST + SGST; otherwise IGST. */
    public static function isIntraState(?string $buyerState): bool
    {
        $seller = config('billing.seller.state');

        return $seller && $buyerState && mb_strtolower(trim($seller)) === mb_strtolower(trim($buyerState));
    }

    public static function newOrder(Workspace $workspace, ?User $user, string $plan, string $cycle, string $gateway): Payment
    {
        $amount = self::price($plan, $cycle);
        $tax = self::gst($amount);

        return Payment::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id, 'user_id' => $user?->id, 'gateway' => $gateway, 'plan' => $plan, 'cycle' => $cycle,
            'amount' => $amount, 'tax' => $tax, 'total' => $amount + $tax,
            'order_id' => 'sd_'.$workspace->id.'_'.Str::lower(Str::random(14)),
        ]);
    }

    /**
     * Mark an order paid (safe to call twice: webhook + browser return), extend the plan and issue the invoice.
     */
    public static function markPaid(Payment $payment, ?string $gatewayPaymentId = null, array $meta = []): Payment
    {
        return DB::transaction(function () use ($payment, $gatewayPaymentId, $meta) {
            $payment = Payment::withoutGlobalScopes()->lockForUpdate()->find($payment->id);
            if ($payment->status === 'paid') {
                return $payment;
            }
            $workspace = Workspace::lockForUpdate()->find($payment->workspace_id);
            [$from, $to] = self::extend($workspace, $payment->plan, $payment->cycle);

            $payment->update([
                'status' => 'paid', 'paid_at' => now(), 'gateway_payment_id' => $gatewayPaymentId ?? $payment->gateway_payment_id,
                'meta' => array_merge($payment->meta ?? [], $meta),
            ]);
            self::issueInvoice($payment, $workspace, $from, $to);

            return $payment;
        });
    }

    public static function markFailed(Payment $payment, array $meta = []): void
    {
        if ($payment->status === 'created') {
            $payment->update(['status' => 'failed', 'meta' => array_merge($payment->meta ?? [], $meta)]);
        }
    }

    /**
     * Give the workspace the plan time it paid for.
     * Same plan: added after the current end date. Different plan: starts today, and the unused value
     * of the old plan becomes extra days on the new one.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function extend(Workspace $workspace, string $plan, string $cycle): array
    {
        $months = config("billing.cycles.{$cycle}.months");
        $active = $workspace->hasActivePlan();

        if ($active && $workspace->plan === $plan) {
            $from = $workspace->plan_ends_at->copy();
            $to = $from->copy()->addMonthsNoOverflow($months);
        } else {
            $from = now();
            $to = $from->copy()->addMonthsNoOverflow($months);
            if ($active) {
                $secondsLeft = now()->diffInSeconds($workspace->plan_ends_at);
                $oldDaily = config("billing.plans.{$workspace->plan}.price") * 12 / 365;
                $newDaily = config("billing.plans.{$plan}.price") * 12 / 365;
                $to->addSeconds((int) round($secondsLeft * $oldDaily / max(1, $newDaily)));
            }
        }

        $workspace->forceFill(['plan' => $plan, 'plan_cycle' => $cycle, 'plan_ends_at' => $to])->save();

        return [$from, $to];
    }

    /** Invoice numbers run per Indian financial year: INV/2026-27/00001. */
    public static function nextInvoiceNumber(Carbon $date): string
    {
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;
        $fy = $startYear.'-'.substr((string) ($startYear + 1), -2);
        $prefix = config('billing.seller.invoice_prefix').'/'.$fy.'/';
        $last = Invoice::withoutGlobalScopes()->where('number', 'like', $prefix.'%')->orderByDesc('id')->value('number');
        $n = $last ? (int) Str::afterLast($last, '/') + 1 : 1;

        return $prefix.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    private static function issueInvoice(Payment $payment, Workspace $workspace, Carbon $from, Carbon $to): Invoice
    {
        $intra = self::isIntraState($workspace->billing_state);
        $tax = (float) $payment->tax;
        $half = round($tax / 2, 2);

        return Invoice::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'payment_id' => $payment->id,
            'number' => self::nextInvoiceNumber(now()),
            'issued_on' => now()->toDateString(),
            'seller' => config('billing.seller'),
            'buyer' => [
                'name' => $workspace->billing_name ?: $workspace->name,
                'gstin' => $workspace->billing_gstin, 'address' => $workspace->billing_address,
                'state' => $workspace->billing_state, 'email' => $workspace->billing_email ?: $workspace->owner?->email,
                'phone' => $workspace->billing_phone,
            ],
            'description' => 'WhatsApp Business Suite: '.$payment->planName().' plan',
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'subtotal' => $payment->amount,
            'cgst' => $intra ? $half : 0,
            'sgst' => $intra ? $tax - $half : 0,
            'igst' => $intra ? 0 : $tax,
            'total' => $payment->total,
        ]);
    }
}
