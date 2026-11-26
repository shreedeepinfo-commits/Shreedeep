<?php

namespace App\Http\Middleware;

use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only workspace owners and admins; agents get a 403. */
class EnsureManager
{
    public function __construct(private CurrentWorkspace $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->current->isManager(), 403, 'Only admins can open this page. Ask your workspace owner for access.');

        return $next($request);
    }
}
