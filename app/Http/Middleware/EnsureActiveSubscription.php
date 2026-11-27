<?php

namespace App\Http\Middleware;

use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the trial or plan has run out the panel stays readable, but nothing can be sent or changed
 * until the workspace renews. Billing, profile and logout keep working.
 */
class EnsureActiveSubscription
{
    private const ALWAYS_ALLOWED = ['subscription.*', 'support.*', 'logout', 'profile.*', 'workspaces.switch', 'admin.impersonate.stop'];

    public function __construct(private CurrentWorkspace $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $this->current->get();
        if ($workspace?->isSuspended() && ! $request->routeIs('logout', 'admin.impersonate.stop')) {
            return response()->view('suspended', ['workspace' => $workspace], 403);
        }
        if (! $workspace || ! $workspace->isExpired() || $request->isMethodSafe() || $request->routeIs(...self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        $message = 'Your '.($workspace->plan_ends_at ? 'plan' : 'free trial').' has ended. Renew to keep sending messages.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 402);
        }

        return redirect()->route($this->current->isManager() ? 'subscription.index' : 'dashboard')->withErrors(['plan' => $message]);
    }
}
