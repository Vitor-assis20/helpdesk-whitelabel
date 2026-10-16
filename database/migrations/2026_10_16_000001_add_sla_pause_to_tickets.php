<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // O prazo de resolução para enquanto o chamado aguarda o cliente.
        Schema::table('tickets', function (Blueprint $table) {
            // Início da pausa em curso (null = relógio correndo).
            $table->timestampTz('sla_paused_at')->nullable();
            // Minutos úteis já pausados, somados ao prazo em recálculos (ex.: troca de prioridade).
            $table->unsignedInteger('sla_paused_minutes')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['sla_paused_at', 'sla_paused_minutes']);
        });
    }
};
