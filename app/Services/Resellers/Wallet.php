<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Workspace;
use App\Services\Billing\Billing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reseller wallet: every change is one locked transaction with a ledger row. Amounts in paise. */
class Wallet
{
    public static function credit(Reseller $reseller, int $paise, string $reason, string $description, array $extra = []): WalletTransaction
    {
        return self::move($reseller, 'credit', $paise, $reason, $description, $extra);
    }

    /** @throws InsufficientBalance */
    public static function debit(Reseller $reseller, int $paise, string $reason, string $description, array $extra = []): WalletTransaction
    {
        return self::move($reseller, 'debit', $paise, $reason, $description, $extra);
    }

    private static function move(Reseller $reseller, string $type, int $paise, string $reason, string $description, array $extra): WalletTransaction
    {
        if ($paise <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }

        return DB::transaction(function () use ($reseller, $type, $paise, $reason, $description, $extra) {
            $locked = Reseller::whereKey($reseller->id)->lockForUpdate()->firstOrFail();
            $balance = $type === 'credit' ? $locked->balance + $paise : $locked->balance - $paise;
            if ($balance < 0) {
                throw new InsufficientBalance($locked, $paise);
            }
            $locked->forceFill(['balance' => $balance])->save();
            $reseller->balance = $balance;

            return WalletTransaction::create([
                'reseller_id' => $reseller->id, 'type' => $type, 'reason' => $reason, 'amount' => $paise,
                'balance_after' => $balance, 'description' => $description,
            ] + $extra);
        });
    }

    /** What the reseller pays for a plan and billing cycle (cycle discounts apply), in paise. */
    public static function cost(Reseller $reseller, string $plan, string $cycle): int
    {
        $c = config("billing.cycles.{$cycle}");

        return (int) round($reseller->cost($plan) * $c['months'] * (100 - $c['discount']));
    }

    /** Charges the reseller's wallet and switches on (or extends) the customer's plan. */
    public static function activate(Reseller $reseller, Workspace $workspace, string $plan, string $cycle, ?User $by = null, string $reason = 'activation'): WalletTransaction
    {
        abort_unless($workspace->reseller_id === $reseller->id, 403);
        if ($reseller->isSuspended()) {
            throw ValidationException::withMessages(['plan' => 'Your reseller account is suspended.']);
        }

        return DB::transaction(function () use ($reseller, $workspace, $plan, $cycle, $by, $reason) {
            $label = config("billing.plans.{$plan}.name").' · '.config("billing.cycles.{$cycle}.label");
            $tx = self::debit($reseller, self::cost($reseller, $plan, $cycle), $reason, "{$label} for {$workspace->name}", [
                'workspace_id' => $workspace->id, 'user_id' => $by?->id,
            ]);
            [, $to] = Billing::extend($workspace, $plan, $cycle);
            $workspace->forceFill(['renew_failed_at' => null])->save();
            $tx->update(['description' => $tx->description.' (till '.$to->format('d M Y').')']);

            return $tx;
        });
    }

    /** Moves balance from a reseller to one of its own sub-resellers. */
    public static function transfer(Reseller $from, Reseller $to, int $paise, ?User $by = null): void
    {
        abort_unless($to->parent_id === $from->id, 403);
        DB::transaction(function () use ($from, $to, $paise, $by) {
            self::debit($from, $paise, 'transfer', "Transfer to {$to->name}", ['related_reseller_id' => $to->id, 'user_id' => $by?->id]);
            self::credit($to, $paise, 'transfer', "Received from {$from->name}", ['related_reseller_id' => $from->id, 'user_id' => $by?->id]);
        });
    }

    /** Rupee input from a form to paise. */
    public static function paise(float|string|null $rupees): int
    {
        return (int) round(((float) $rupees) * 100);
    }
}
