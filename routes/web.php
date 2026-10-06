<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// Destino padrão após o login.
Route::middleware(['auth'])->get('dashboard', fn () => to_route('panel.tickets.index'))->name('dashboard');

require __DIR__.'/panel.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
