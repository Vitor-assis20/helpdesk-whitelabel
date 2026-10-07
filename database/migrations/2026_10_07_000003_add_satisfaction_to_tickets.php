<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Avaliação do atendimento pelo cliente (CSAT), depois de resolvido.
        // Uma por chamado: avaliar de novo substitui a anterior (o histórico guarda as duas).
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedSmallInteger('satisfaction_score')->nullable();
            $table->text('satisfaction_comment')->nullable();
            $table->timestampTz('satisfaction_rated_at')->nullable();
        });

        DB::statement('ALTER TABLE tickets ADD CONSTRAINT tickets_satisfaction_score_range CHECK (satisfaction_score BETWEEN 1 AND 5)');
        // Nota e data andam juntas; comentário só acompanha uma nota.
        DB::statement('ALTER TABLE tickets ADD CONSTRAINT tickets_satisfaction_consistent CHECK ((satisfaction_score IS NULL) = (satisfaction_rated_at IS NULL) AND (satisfaction_comment IS NULL OR satisfaction_score IS NOT NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['satisfaction_score', 'satisfaction_comment', 'satisfaction_rated_at']);
        });
    }
};
