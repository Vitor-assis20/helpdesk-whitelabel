<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes, chamados, mensagens e histórico.
 *
 * Como no restante do sistema, as tabelas de tenant expõem UNIQUE (tenant_id, id)
 * e as filhas usam chaves estrangeiras compostas: o banco impede que um registro
 * aponte para outro de uma empresa diferente.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Número exibido (#42), sequencial dentro de cada empresa.
            $table->unsignedInteger('number');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->string('subject', 200);
            $table->string('status')->default('new');
            $table->string('priority')->default('normal');
            $table->string('channel');
            $table->timestampTz('first_response_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('last_activity_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->index(['tenant_id', 'status', 'last_activity_at']);
            $table->index(['tenant_id', 'assignee_id']);
        });

        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status IN ('new', 'open', 'pending', 'resolved', 'closed'))");
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_priority_check CHECK (priority IN ('low', 'normal', 'high', 'urgent'))");
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_channel_check CHECK (channel IN ('portal', 'widget', 'email', 'panel'))");

        // O responsável precisa ser da equipe da mesma empresa. Se ele sair da
        // equipe, só assignee_id vira NULL (lista de colunas: Postgres 15+);
        // um SET NULL comum anularia também o tenant_id.
        DB::statement(<<<'SQL'
            ALTER TABLE tickets ADD CONSTRAINT tickets_assignee_member_fk
            FOREIGN KEY (tenant_id, assignee_id) REFERENCES tenant_user (tenant_id, user_id)
            ON DELETE SET NULL (assignee_id)
        SQL);

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('ticket_id');
            // Autor: um atendente, um cliente ou nenhum (mensagem do sistema).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('kind');
            $table->text('body');
            $table->timestamps();

            $table->foreign(['tenant_id', 'ticket_id'])->references(['tenant_id', 'id'])->on('tickets')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->index(['ticket_id', 'id']);
        });

        DB::statement("ALTER TABLE ticket_messages ADD CONSTRAINT ticket_messages_kind_check CHECK (kind IN ('reply', 'note'))");
        DB::statement('ALTER TABLE ticket_messages ADD CONSTRAINT ticket_messages_single_author CHECK (num_nonnulls(user_id, customer_id) <= 1)');
        // Nota interna é da equipe: cliente nunca escreve (nem lê) nota.
        DB::statement("ALTER TABLE ticket_messages ADD CONSTRAINT ticket_messages_note_by_team CHECK (kind <> 'note' OR customer_id IS NULL)");

        // Histórico auditável: só inserção.
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('ticket_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->jsonb('data')->default('{}');
            $table->timestampTz('created_at');

            $table->foreign(['tenant_id', 'ticket_id'])->references(['tenant_id', 'id'])->on('tickets')->cascadeOnDelete();
            $table->index(['ticket_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('customers');
    }
};
