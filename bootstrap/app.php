<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolvePanelTenant;
use App\Http\Middleware\ResolvePortalTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'panel' => ResolvePanelTenant::class,
            'portal' => ResolvePortalTenant::class,
        ]);
        // Antes do route model binding: /chamados/{numero} já busca dentro da empresa.
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolvePanelTenant::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolvePortalTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
