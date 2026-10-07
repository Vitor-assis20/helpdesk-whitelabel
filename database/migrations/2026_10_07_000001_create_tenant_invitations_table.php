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
        // Convites para a equipe. O link leva um token aleatório; aqui fica só o hash
        // dele, então quem lê o banco não consegue entrar na equipe.
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestamps();
        });

        // Dono não se convida: a empresa nasce com ele.
        DB::statement("ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_role_check CHECK (role IN ('admin', 'agent'))");
        DB::statement('ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_email_lowercase CHECK (email = lower(email))');
        // Um convite pendente por e-mail em cada empresa; convidar de novo renova o mesmo.
        DB::statement('CREATE UNIQUE INDEX tenant_invitations_pending_unique ON tenant_invitations (tenant_id, email) WHERE accepted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
