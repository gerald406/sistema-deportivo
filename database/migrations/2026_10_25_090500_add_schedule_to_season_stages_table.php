<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fecha, hora y sede de una fase de podio directo (D32): atletismo,
     * ajedrez y lo demas que no tiene partidos. Las fases de partidos no las
     * usan (cada partido se programa en /partidos). Vacio = sin programar.
     */
    public function up(): void
    {
        Schema::table('season_stages', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable()->after('has_third_place');
            $table->foreignId('venue_id')->nullable()->after('scheduled_at')->constrained('venues')->nullOnDelete();

            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('season_stages', function (Blueprint $table) {
            $table->dropIndex(['scheduled_at']);
            $table->dropConstrainedForeignId('venue_id');
            $table->dropColumn('scheduled_at');
        });
    }
};
