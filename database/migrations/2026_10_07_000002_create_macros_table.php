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
        // Respostas prontas da equipe, com variáveis ({{cliente.nome}}...) preenchidas no chamado.
        Schema::create('macros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80);
            $table->text('body');
            // Quantas respostas foram enviadas com ela: as mais usadas aparecem primeiro.
            $table->unsignedInteger('uses')->default(0);
            $table->timestamps();
        });

        // O título é o que se digita depois da "/": único na empresa, sem diferenciar maiúsculas.
        DB::statement('CREATE UNIQUE INDEX macros_tenant_title_unique ON macros (tenant_id, lower(title))');
        // Algum caractere visível (btrim só tiraria espaços, não quebras de linha).
        DB::statement("ALTER TABLE macros ADD CONSTRAINT macros_not_blank CHECK (title ~ '\\S' AND body ~ '\\S')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('macros');
    }
};
