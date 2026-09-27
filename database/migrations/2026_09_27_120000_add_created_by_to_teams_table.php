<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mismo motivo que players.created_by: Team no tiene relacion directa
     * con Tournament (solo via season_team -> seasons -> tournaments). El
     * organizador solo gestiona los equipos "a su cargo" (inscritos en una
     * temporada de un torneo suyo), pero un equipo que acaba de crear aun
     * no tiene inscripciones: sin esta columna desapareceria de /equipos
     * apenas lo guarda. Ver TeamRegistrationService::scopeManagedBy().
     * Los equipos existentes quedan con NULL (solo el admin los creo o se
     * crearon antes de esta regla).
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('is_active')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
