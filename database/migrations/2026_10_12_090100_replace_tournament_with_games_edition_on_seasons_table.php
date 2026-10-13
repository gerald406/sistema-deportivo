<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una competencia (seasons) pertenece siempre a una olimpiada: se
     * quita tournament_id y games_edition_id deja de ser opcional.
     */
    public function up(): void
    {
        if (DB::table('seasons')->whereNull('games_edition_id')->exists()) {
            throw new RuntimeException('Hay temporadas sin olimpiada (games_edition_id NULL): asígnales una antes de migrar.');
        }

        Schema::table('seasons', function (Blueprint $table) {
            $table->dropForeign(['tournament_id']);
            $table->dropForeign(['games_edition_id']);
        });

        Schema::table('seasons', function (Blueprint $table) {
            $table->dropUnique(['tournament_id', 'name']);
            $table->dropColumn('tournament_id');
        });

        Schema::table('seasons', function (Blueprint $table) {
            $table->unsignedBigInteger('games_edition_id')->nullable(false)->change();
            // RESTRICT (antes SET NULL): una olimpiada con competencias no se borra.
            $table->foreign('games_edition_id')->references('id')->on('games_editions')->restrictOnDelete();
            $table->unique(['games_edition_id', 'name']);
        });
    }

    /**
     * Solo restaura la estructura: tournament_id vuelve como nullable
     * porque los torneos originales ya no existen.
     */
    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropForeign(['games_edition_id']);
        });

        Schema::table('seasons', function (Blueprint $table) {
            $table->dropUnique(['games_edition_id', 'name']);
            $table->unsignedBigInteger('games_edition_id')->nullable()->change();
            $table->foreign('games_edition_id')->references('id')->on('games_editions')->nullOnDelete();
            $table->foreignId('tournament_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->unique(['tournament_id', 'name']);
        });
    }
};
