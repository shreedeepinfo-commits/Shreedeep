<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReseller
{
    public function handle(Request $request, Closure $next): Response
    {
        $reseller = $request->user()?->reseller;
        abort_unless($reseller, 404);
        if ($reseller->isSuspended()) {
            return response()->view('suspended', ['reseller' => true], 403);
        }
        app()->instance('reseller', $reseller);

        return $next($request);
    }
}
