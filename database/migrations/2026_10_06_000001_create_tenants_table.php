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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->jsonb('branding')->default('{}');
            // Último número de chamado emitido; incrementado de forma atômica.
            $table->unsignedInteger('ticket_sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Slug vira subdomínio: só minúsculas, dígitos e hífens internos.
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_slug_format CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");

        // Membros da equipe. A PK (tenant_id, user_id) é alvo das FKs de responsável.
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->primary(['tenant_id', 'user_id']);
        });

        DB::statement("ALTER TABLE tenant_user ADD CONSTRAINT tenant_user_role_check CHECK (role IN ('owner', 'admin', 'agent'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenants');
    }
};
