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
        // Expediente semanal da empresa, em horário local (weekday: 0 = domingo).
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['tenant_id', 'weekday']);
        });

        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_weekday_range CHECK (weekday BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE business_hours ADD CONSTRAINT business_hours_valid_range CHECK (ends_at > starts_at)');

        // Prazos por prioridade, em minutos úteis.
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('priority');
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->timestamps();

            $table->unique(['tenant_id', 'priority']);
        });

        DB::statement("ALTER TABLE sla_policies ADD CONSTRAINT sla_policies_priority_check CHECK (priority IN ('low', 'normal', 'high', 'urgent'))");
        DB::statement('ALTER TABLE sla_policies ADD CONSTRAINT sla_policies_positive CHECK (first_response_minutes > 0 AND resolution_minutes >= first_response_minutes)');

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestampTz('first_response_due_at')->nullable();
            $table->timestampTz('resolution_due_at')->nullable();
            // Quando o estouro foi detectado (e notificado) — uma vez só.
            $table->timestampTz('first_response_breached_at')->nullable();
            $table->timestampTz('resolution_breached_at')->nullable();

            $table->index(['first_response_due_at']);
            $table->index(['resolution_due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['first_response_due_at', 'resolution_due_at', 'first_response_breached_at', 'resolution_breached_at']);
        });
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('business_hours');
    }
};
