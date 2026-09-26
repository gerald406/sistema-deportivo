<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_team_id')->constrained('season_team')->cascadeOnDelete();
            $table->integer('matches_played')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('draws')->default(0);
            $table->integer('losses')->default(0);
            $table->integer('walkovers_against')->default(0);
            $table->integer('points')->default(0);
            $table->json('extra_stats')->nullable()->comment('Criterios de desempate: diferencia de goles, de sets, etc.');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable()->comment('Fecha del ultimo recalculo (Job/Observer)');

            $table->unique(['season_id', 'season_team_id']);
        });

        DB::statement("ALTER TABLE season_standings ADD CONSTRAINT chk_standings_extra_stats_json CHECK (extra_stats IS NULL OR JSON_TYPE(extra_stats) = 'OBJECT')");
    }

    public function down(): void
    {
        Schema::dropIfExists('season_standings');
    }
};
