<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Super admins and resellers open their own WhatsApp panel (their own business workspace) in one click. */
class MyPanelController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user->is_super_admin || $user->reseller_id, 404);

        $workspace = $user->workspaces()->wherePivot('role', 'owner')->first();
        if (! $workspace) {
            $reseller = $user->reseller;
            $workspace = $user->createWorkspace($reseller?->name ?? config('brand.name'));
            $workspace->forceFill($user->is_super_admin
                ? ['plan' => 'pro', 'plan_cycle' => 'yearly', 'plan_ends_at' => now()->addYears(10)] // the platform owner's own business
                : ['reseller_id' => $reseller->id, 'trial_ends_at' => now()->addDays(config('billing.trial_days'))]
            )->save();
        }
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect()->route('dashboard')->with('status', 'This is your own WhatsApp panel. Connect your number in WhatsApp Settings.');
    }
}
