<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** "Authorization: Bearer sdk_..." → the key's workspace becomes the current tenant. */
class AuthenticateApiKey
{
    public function __construct(private CurrentWorkspace $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = ApiKey::findActive($request->bearerToken() ?? $request->header('X-Api-Key'));
        if (! $key) {
            return response()->json(['error' => ['code' => 'unauthorized', 'message' => 'Missing or invalid API key.']], 401);
        }
        $workspace = Workspace::find($key->workspace_id);
        if (! $workspace || $workspace->isSuspended()) {
            return response()->json(['error' => ['code' => 'account_suspended', 'message' => 'This account is suspended.']], 403);
        }
        if ($workspace->isExpired() && ! $request->isMethodSafe()) {
            return response()->json(['error' => ['code' => 'plan_expired', 'message' => 'Your plan has ended. Renew it to use the API.']], 402);
        }

        $this->current->set($workspace, 'api');
        $request->attributes->set('api_key_id', $key->id);
        ApiKey::withoutGlobalScopes()->whereKey($key->id)->update(['last_used_at' => now(), 'requests' => $key->requests + 1]);

        return $next($request);
    }
}
