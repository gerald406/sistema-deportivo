<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La olimpiada guarda solo sus datos basicos: nombre, organizador,
     * fechas, logo y estado.
     *   - host_venue_id: nada la usaba (cada partido elige su sede).
     *   - yellow_cards_limit / captain_red_matches: la acumulacion de
     *     amarillas y la sancion especial al capitan se manejan fuera del
     *     sistema (decision del cliente).
     *   - start_date / end_date pasan a ser obligatorias.
     */
    public function up(): void
    {
        Schema::table('games_editions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('host_venue_id');
            $table->dropColumn(['yellow_cards_limit', 'captain_red_matches']);
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('games_editions', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
            $table->foreignId('host_venue_id')->nullable()->after('end_date')->constrained('venues')->nullOnDelete();
            $table->integer('yellow_cards_limit')->nullable()->after('is_active')
                ->comment('Amarillas acumuladas en una competencia que dan 1 partido de sancion');
            $table->integer('captain_red_matches')->nullable()->after('yellow_cards_limit')
                ->comment('Partidos de sancion al capitan expulsado (los demas: 1)');
        });
    }
};
