<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El puntaje general ordena por puntos; oros, platas y bronces quedan
     * como desempate. Dato derivado (AwardService::rebuild()).
     */
    public function up(): void
    {
        Schema::table('games_edition_medals', function (Blueprint $table) {
            $table->integer('total_points')->default(0)->after('bronze');
        });
    }

    public function down(): void
    {
        Schema::table('games_edition_medals', function (Blueprint $table) {
            $table->dropColumn('total_points');
        });
    }
};
