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
        $workspace = $user?->currentWorkspace;

        if ($user && (! $workspace || ! $user->belongsToWorkspace($workspace))) {
            $workspace = $user->workspaces()->first() ?? $user->createWorkspace($user->name."'s workspace");
            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        }

        $this->current->set($workspace);
        View::share('currentWorkspace', $workspace);

        return $next($request);
    }
}
