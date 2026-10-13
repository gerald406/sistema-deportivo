<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partidos de llave (eliminacion simple, Art. 8 y 10 de las bases):
     * a que fase pertenecen, su lugar en la llave, a que partido pasa el
     * ganador (y el perdedor, si hay tercer puesto) y por que via se
     * desempato. event_participants.tiebreak_score guarda el marcador del
     * desempate (penales, tiros libres), si se anoto.
     *
     * Los triggers de disciplina/deporte sobre matches no dependen de
     * estas columnas y sobreviven al ALTER.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE matches MODIFY round_type ENUM('regular','heat','quarterfinal','semifinal','final','third_place') NOT NULL DEFAULT 'regular'");

        Schema::table('matches', function (Blueprint $table) {
            $table->foreignId('season_stage_id')->nullable()->after('matchday_id')
                ->constrained('season_stages')->restrictOnDelete();
            $table->integer('bracket_round')->nullable()->after('round_type')->comment('1 = primera ronda');
            $table->integer('bracket_slot')->nullable()->after('bracket_round')->comment('Posicion dentro de la ronda, desde 0');
            $table->foreignId('next_match_id')->nullable()->after('bracket_slot')->constrained('matches')->nullOnDelete();
            $table->enum('next_match_side', ['home', 'away'])->nullable()->after('next_match_id');
            $table->foreignId('loser_next_match_id')->nullable()->after('next_match_side')->constrained('matches')->nullOnDelete();
            $table->enum('tiebreak_type', ['extra_time', 'penalties', 'free_throws', 'coin_toss'])->nullable()->after('status');
        });

        Schema::table('event_participants', function (Blueprint $table) {
            $table->integer('tiebreak_score')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropColumn('tiebreak_score');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['season_stage_id']);
            $table->dropForeign(['next_match_id']);
            $table->dropForeign(['loser_next_match_id']);
            $table->dropColumn(['season_stage_id', 'bracket_round', 'bracket_slot', 'next_match_id', 'next_match_side', 'loser_next_match_id', 'tiebreak_type']);
        });

        DB::table('matches')->where('round_type', 'third_place')->update(['round_type' => 'final']);
        DB::statement("ALTER TABLE matches MODIFY round_type ENUM('regular','heat','quarterfinal','semifinal','final') NOT NULL DEFAULT 'regular'");
    }
};
