<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_lineups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_participant_id')->constrained('event_participants')->cascadeOnDelete();
            $table->foreignId('season_team_player_id')->constrained('season_team_player')->restrictOnDelete();
            $table->unsignedTinyInteger('leg_order')->comment('Orden del tramo dentro de la posta (1, 2, 3, 4)');
            $table->timestamps();

            $table->unique(['event_participant_id', 'leg_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_lineups');
    }
};
