<?php

use App\Http\Controllers\Portal\BroadcastAuthController;
use App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;

// Portal do cliente, em {slug}.APP_DOMAIN.
Route::domain('{tenant}.'.config('app.domain'))
    ->middleware('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', [PortalController::class, 'home'])->name('home');

        Route::post('chamados', [PortalController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('tickets.store');

        // O token (40 caracteres aleatórios) é o segredo do link enviado ao cliente.
        Route::get('chamados/{ticket:access_token}', [PortalController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('tickets.show');

        Route::post('chamados/{ticket:access_token}/mensagens', [PortalController::class, 'reply'])
            ->middleware('throttle:10,1')
            ->name('tickets.reply');

        Route::post('portal/broadcasting/auth', BroadcastAuthController::class)
            ->middleware('throttle:60,1')
            ->name('broadcasting.auth');
    });
