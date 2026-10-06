<?php

use App\Http\Controllers\Panel\SettingsController;
use App\Http\Controllers\Panel\SwitchTenantController;
use App\Http\Controllers\Panel\TicketActionController;
use App\Http\Controllers\Panel\TicketController;
use Illuminate\Support\Facades\Route;

// Painel da equipe.
Route::middleware(['auth', 'verified', 'panel'])
    ->prefix('painel')
    ->name('panel.')
    ->group(function () {
        Route::get('chamados', [TicketController::class, 'index'])->name('tickets.index');
        // O número do chamado (#42) é único dentro da empresa; o binding consulta já no escopo dela.
        Route::get('chamados/{ticket:number}', [TicketController::class, 'show'])->name('tickets.show');

        Route::post('chamados/{ticket:number}/mensagens', [TicketActionController::class, 'reply'])->name('tickets.reply');
        Route::patch('chamados/{ticket:number}/status', [TicketActionController::class, 'status'])->name('tickets.status');
        Route::patch('chamados/{ticket:number}/responsavel', [TicketActionController::class, 'assign'])->name('tickets.assign');
        Route::patch('chamados/{ticket:number}/prioridade', [TicketActionController::class, 'priority'])->name('tickets.priority');

        Route::post('empresa', SwitchTenantController::class)->name('switch-tenant');

        Route::middleware('can:manage-tenant')->prefix('configuracoes')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'edit'])->name('edit');
            Route::put('marca', [SettingsController::class, 'branding'])->name('branding');
            Route::put('expediente', [SettingsController::class, 'hours'])->name('hours');
            Route::put('sla', [SettingsController::class, 'sla'])->name('sla');
        });
    });
