<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'workspace' => \App\Http\Middleware\SetCurrentWorkspace::class,
            'manager' => \App\Http\Middleware\EnsureManager::class,
            'subscribed' => \App\Http\Middleware\EnsureActiveSubscription::class,
            'super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'api.key' => \App\Http\Middleware\AuthenticateApiKey::class,
        ]);
        // Resolve the workspace before route model binding so bound models are tenant-scoped.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\SetCurrentWorkspace::class,
        );
        $middleware->preventRequestForgery(except: ['webhooks/*']);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
