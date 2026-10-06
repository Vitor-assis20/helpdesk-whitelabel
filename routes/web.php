<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/portal.php';

// Plataforma (site, login e painel) só no domínio principal; os subdomínios
// ficam exclusivos para o portal de cada empresa.
Route::domain(config('app.domain'))->group(function () {
    Route::get('/', function () {
        return Inertia::render('welcome');
    })->name('home');

    // Destino padrão após o login.
    Route::middleware(['auth'])->get('dashboard', fn () => to_route('panel.tickets.index'))->name('dashboard');

    require __DIR__.'/panel.php';
    require __DIR__.'/settings.php';
    require __DIR__.'/auth.php';
});
