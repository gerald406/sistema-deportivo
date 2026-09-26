<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->string('participant_type', 100)->comment("'season_team' o 'season_team_player' (morphMap)");
            $table->unsignedBigInteger('participant_id');
            $table->enum('side', ['home', 'away'])->nullable()->comment('Solo aplica si el deporte es head_to_head');
            $table->string('lane_or_board', 20)->nullable()->comment('Carril, calle o tablero/color');
            $table->decimal('result_value', 12, 4)->nullable()->comment('Tiempo, distancia o puntos, segun la disciplina');
            $table->integer('position')->nullable()->comment('Puesto final obtenido');
            $table->timestamps();

            $table->index(['participant_type', 'participant_id']);
            $table->index(['match_id', 'position']);
        });

        DB::statement("ALTER TABLE event_participants ADD CONSTRAINT chk_event_participants_type CHECK (participant_type IN ('season_team', 'season_team_player'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
