<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Segredo do link que o cliente usa para acompanhar o chamado (não há login de cliente).
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('access_token', 40)->nullable()->unique();
        });

        DB::table('tickets')->whereNull('access_token')->orderBy('id')->each(
            fn (object $ticket) => DB::table('tickets')->where('id', $ticket->id)->update(['access_token' => Str::random(40)]),
        );

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('access_token', 40)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('access_token');
        });
    }
};
