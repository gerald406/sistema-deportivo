<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reglas disciplinarias de la olimpiada como datos (Art. 14 y 15 de las
     * bases de Puno 2026: 2 amarillas acumuladas = 1 partido; capitan
     * expulsado = 2 partidos). NULL = la regla no se aplica.
     */
    public function up(): void
    {
        Schema::table('games_editions', function (Blueprint $table) {
            $table->integer('yellow_cards_limit')->nullable()->after('is_active')
                ->comment('Amarillas acumuladas en una competencia que dan 1 partido de sancion');
            $table->integer('captain_red_matches')->nullable()->after('yellow_cards_limit')
                ->comment('Partidos de sancion al capitan expulsado (los demas: 1)');
        });
    }

    public function down(): void
    {
        Schema::table('games_editions', function (Blueprint $table) {
            $table->dropColumn(['yellow_cards_limit', 'captain_red_matches']);
        });
    }
};
