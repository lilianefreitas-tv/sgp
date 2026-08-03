<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureOrganizationContext;
use App\Http\Middleware\EnsureUserIsAdministrator;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'administrator' => EnsureUserIsAdministrator::class,
            'organization' => EnsureOrganizationContext::class,
        ]);

        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureOrganizationContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
