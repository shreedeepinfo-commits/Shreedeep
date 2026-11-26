<?php

namespace App\Http\Controllers;

use App\Support\CurrentWorkspace;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    /**
     * Stop when adding $count more of $key (numbers, members, contacts, flows) would pass the plan limit.
     * Returns a response to send back, or null when there is room.
     */
    protected function planLimitReached(string $key, int $count = 1, string $field = 'plan'): ?Response
    {
        $workspace = app(CurrentWorkspace::class)->get();
        if (! $workspace || $workspace->canAdd($key, $count)) {
            return null;
        }
        $message = $workspace->limitMessage($key);

        return request()->expectsJson()
            ? response()->json(['message' => $message], 422)
            : back()->withErrors([$field => $message])->withInput();
    }
}
