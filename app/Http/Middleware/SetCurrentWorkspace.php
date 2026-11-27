<?php

namespace App\Http\Middleware;

use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentWorkspace
{
    public function __construct(private CurrentWorkspace $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->is_super_admin && ! $user->workspaces()->exists()) {
            return redirect()->route('admin.dashboard'); // the platform owner uses the admin panel until they open their own WhatsApp panel
        }
        if ($user?->reseller_id && ! $user->workspaces()->exists()) {
            return redirect()->route('reseller.dashboard'); // a reseller login without its own WhatsApp workspace
        }
        $workspace = $user?->currentWorkspace;

        if ($user && (! $workspace || ! $user->belongsToWorkspace($workspace))) {
            $workspace = $user->workspaces()->first() ?? $user->createWorkspace($user->name."'s workspace");
            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        }

        $role = $user && $workspace
            ? $user->workspaces()->whereKey($workspace->id)->first()?->pivot->role
            : null;
        $this->current->set($workspace, $role);
        View::share('currentWorkspace', $workspace);
        View::share('isManager', $this->current->isManager());

        return $next($request);
    }
}
