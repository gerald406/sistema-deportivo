<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La olimpiada (games_editions) pasa a ser el eje del sistema: hereda
     * de tournaments el organizador responsable y el logo. tournaments se
     * elimina en una migracion posterior.
     */
    public function up(): void
    {
        Schema::table('games_editions', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('name');
            // RESTRICT: un usuario que organiza una olimpiada no se borra
            // (mismo criterio que tenia tournaments.organizer_id).
            $table->foreignId('organizer_id')->nullable()->after('logo')->constrained('users')->restrictOnDelete();
        });

        // Ediciones ya existentes: heredan el organizador del torneo que
        // hubiera; si no hay torneos, el primer usuario (el admin inicial).
        $organizerId = (Schema::hasTable('tournaments') ? DB::table('tournaments')->orderBy('id')->value('organizer_id') : null)
            ?? DB::table('users')->min('id');

        if ($organizerId) {
            DB::table('games_editions')->whereNull('organizer_id')->update(['organizer_id' => $organizerId]);
        }

        if (! DB::table('games_editions')->whereNull('organizer_id')->exists()) {
            Schema::table('games_editions', function (Blueprint $table) {
                $table->unsignedBigInteger('organizer_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('games_editions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizer_id');
            $table->dropColumn('logo');
        });
    }
};
