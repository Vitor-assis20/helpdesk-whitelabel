<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Busca full-text no próprio Postgres.
 *
 * pt_unaccent = configuração "portuguese" (radicais: reembolsos → reembolso)
 * com unaccent antes: quem digita "cobranca" encontra "Cobrança".
 * Os vetores são colunas geradas pelo banco, sempre em dia com o texto,
 * e indexadas com GIN.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        // Sem "IF NOT EXISTS" para configurações de busca, e o migrate:fresh apaga
        // tabelas mas não configurações: verifica antes de criar.
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_ts_config WHERE cfgname = 'pt_unaccent') THEN
                    CREATE TEXT SEARCH CONFIGURATION pt_unaccent (COPY = portuguese);
                    ALTER TEXT SEARCH CONFIGURATION pt_unaccent ALTER MAPPING FOR hword, hword_part, word WITH unaccent, portuguese_stem;
                END IF;
            END
            $$
        SQL);

        DB::statement("ALTER TABLE tickets ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('pt_unaccent', coalesce(subject, ''))) STORED");
        DB::statement('CREATE INDEX tickets_search_vector_index ON tickets USING gin (search_vector)');

        DB::statement("ALTER TABLE ticket_messages ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('pt_unaccent', coalesce(body, ''))) STORED");
        DB::statement('CREATE INDEX ticket_messages_search_vector_index ON ticket_messages USING gin (search_vector)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE ticket_messages DROP COLUMN IF EXISTS search_vector');
        DB::statement('ALTER TABLE tickets DROP COLUMN IF EXISTS search_vector');
        DB::statement('DROP TEXT SEARCH CONFIGURATION IF EXISTS pt_unaccent');
    }
};
