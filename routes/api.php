<?php

use App\Http\Controllers\Portal\BroadcastAuthController;
use App\Http\Controllers\Widget\WidgetScriptController;
use App\Http\Controllers\Widget\WidgetTicketController;
use Illuminate\Support\Facades\Route;

/*
| API do widget, em {empresa}.APP_DOMAIN/api. Grupo "api": sem sessão, sem
| cookies e sem CSRF; CORS liberado para qualquer origem (config/cors.php),
| porque o widget roda no site da empresa. O acesso a um chamado é pelo token.
*/
Route::domain('{tenant}.'.config('app.domain'))
    ->middleware('portal')
    ->name('widget.')
    ->group(function () {
        Route::get('widget.js', WidgetScriptController::class)->name('script');

        Route::post('widget/tickets', [WidgetTicketController::class, 'store'])->middleware('throttle:5,1')->name('tickets.store');
        Route::get('widget/tickets/{ticket:access_token}', [WidgetTicketController::class, 'show'])->middleware('throttle:60,1')->name('tickets.show');
        Route::post('widget/tickets/{ticket:access_token}/messages', [WidgetTicketController::class, 'reply'])->middleware('throttle:10,1')->name('tickets.reply');

        Route::post('widget/tickets/{ticket:access_token}/rating', [WidgetTicketController::class, 'rate'])->middleware('throttle:10,1')->name('tickets.rate');

        Route::post('widget/broadcasting/auth', BroadcastAuthController::class)->middleware('throttle:60,1')->name('broadcasting.auth');
    });
