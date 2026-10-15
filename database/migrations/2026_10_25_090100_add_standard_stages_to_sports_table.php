<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fases que arma "Crear fases estandar" segun el deporte (D18). NULL =
     * se deduce del formato (enfrentamiento: series en llave + fase final;
     * lo demas: solo podio). Ajedrez se juega en series por sistema suizo
     * fuera del sistema (bases 2.2): series de podio + final.
     */
    public function up(): void
    {
        Schema::table('sports', function (Blueprint $table) {
            $table->enum('standard_stages', ['bracket_series', 'podium_series', 'podium_only'])
                ->nullable()->after('format_type')
                ->comment('Fases estandar; NULL = segun el formato');
        });

        DB::table('sports')->where('slug', 'ajedrez')->update(['standard_stages' => 'podium_series']);
    }

    public function down(): void
    {
        Schema::table('sports', function (Blueprint $table) {
            $table->dropColumn('standard_stages');
        });
    }
};
