<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\Resellers\InsufficientBalance;
use App\Services\Resellers\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** Renews reseller customers' plans from the reseller's wallet shortly before they end. */
class RenewResellerCustomers extends Command
{
    protected $signature = 'resellers:renew';

    protected $description = 'Auto renew plans of reseller customers from the reseller wallet';

    public function handle(): int
    {
        $renewed = $failed = 0;
        Workspace::query()
            ->whereNotNull('reseller_id')->where('auto_renew', true)->whereNull('suspended_at')
            ->whereNotNull('plan')->whereNotNull('plan_ends_at')
            ->where('plan_ends_at', '<=', now()->addHours(config('reseller.renew_before_hours')))
            ->where('plan_ends_at', '>', now()->subDays(3)) // stop trying 3 days after it ended
            ->where(fn ($q) => $q->whereNull('renew_failed_at')->orWhere('renew_failed_at', '<', now()->subHours(6)))
            ->with('reseller')
            ->each(function (Workspace $w) use (&$renewed, &$failed) {
                if (! $w->reseller || $w->reseller->isSuspended()) {
                    return;
                }
                try {
                    Wallet::activate($w->reseller, $w, $w->plan, $w->plan_cycle ?: 'monthly', null, 'renewal');
                    $renewed++;
                } catch (InsufficientBalance) {
                    $w->forceFill(['renew_failed_at' => now()])->save();
                    $failed++;
                } catch (\Throwable $e) {
                    Log::warning('Reseller renewal failed', ['workspace' => $w->id, 'error' => $e->getMessage()]);
                    $failed++;
                }
            });

        $this->info("Renewed {$renewed}, failed {$failed}.");

        return self::SUCCESS;
    }
}
