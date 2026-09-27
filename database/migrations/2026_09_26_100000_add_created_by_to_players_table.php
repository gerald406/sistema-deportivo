<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * created_by resuelve una ventana de autorizacion real: Player es un
     * catalogo global sin equipo hasta que se inscribe en una temporada
     * (season_team_player, Fase 5). Sin esta columna, el delegado que
     * registra un jugador nuevo no podria editarlo hasta que existiera
     * esa inscripcion. PlayerPolicy::belongsToDelegate() ahora reconoce
     * tanto "esta en el roster de mi equipo" como "yo lo registre".
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('is_active')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
