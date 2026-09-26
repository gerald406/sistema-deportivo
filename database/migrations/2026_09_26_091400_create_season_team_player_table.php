<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_team_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_team_id')->constrained('season_team')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->restrictOnDelete();
            $table->integer('shirt_number')->nullable();
            $table->foreignId('position_id')->nullable()->constrained('sport_positions')->nullOnDelete();
            $table->boolean('is_captain')->default(false);
            $table->enum('status', ['active', 'injured', 'suspended', 'inactive'])->default('active');
            $table->date('enrolled_at');
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->unique(['season_team_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_team_player');
    }
};
