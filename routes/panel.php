<?php

use App\Http\Controllers\Panel\MacroController;
use App\Http\Controllers\Panel\SettingsController;
use App\Http\Controllers\Panel\SwitchTenantController;
use App\Http\Controllers\Panel\TeamController;
use App\Http\Controllers\Panel\TenantController;
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

        Route::middleware('can:manage-tenant')->prefix('respostas')->name('macros.')->group(function () {
            Route::get('/', [MacroController::class, 'index'])->name('index');
            Route::post('/', [MacroController::class, 'store'])->name('store');
            Route::put('{macro}', [MacroController::class, 'update'])->name('update');
            Route::delete('{macro}', [MacroController::class, 'destroy'])->name('destroy');
        });

        Route::middleware('can:manage-tenant')->prefix('equipe')->name('team.')->group(function () {
            Route::get('/', [TeamController::class, 'index'])->name('index');
            Route::post('convites', [TeamController::class, 'invite'])->middleware('throttle:20,1')->name('invite');
            Route::delete('convites/{invitation}', [TeamController::class, 'revoke'])->name('revoke');
            Route::patch('membros/{member}', [TeamController::class, 'updateRole'])->name('role');
            Route::delete('membros/{member}', [TeamController::class, 'remove'])->name('remove');
        });
    });

// Cadastro de empresa: fora do grupo "panel", que exige já fazer parte de uma.
Route::middleware(['auth', 'verified', 'throttle:5,1'])
    ->post('painel/empresas', [TenantController::class, 'store'])
    ->name('panel.tenants.store');
