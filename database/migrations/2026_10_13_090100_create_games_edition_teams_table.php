<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inscripcion de una delegacion (teams) en una olimpiada: su serie, su
     * delegado titular y suplente, y su estado. Es la UNICA fuente de
     * "quien es delegado de quien" (ver SeasonTeam::hasDelegate()).
     */
    public function up(): void
    {
        Schema::create('games_edition_teams', function (Blueprint $table) {
            $table->id();
            // RESTRICT: una olimpiada o delegacion con inscripciones no se borra.
            $table->foreignId('games_edition_id')->constrained('games_editions')->restrictOnDelete();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            // RESTRICT: una serie con delegaciones no se borra (EditionGroupService lo avisa antes).
            $table->foreignId('group_id')->nullable()->constrained('games_edition_groups')->restrictOnDelete();
            $table->foreignId('delegate_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('substitute_delegate_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['registered', 'withdrawn', 'disqualified'])->default('registered');
            $table->boolean('fee_paid')->default(false)->comment('Pago de inscripcion registrado por el organizador');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->unique(['games_edition_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games_edition_teams');
    }
};
