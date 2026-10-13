<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SeasonStatus;
use App\Models\Discipline;
use App\Models\EventParticipant;
use App\Models\GameMatch;
use App\Models\GamesEdition;
use App\Models\GamesEditionTeam;
use App\Models\Matchday;
use App\Models\Player;
use App\Models\ScoringConfig;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\Models\SeasonTeamPlayer;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Services\ResultService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Olimpiada de DEMOSTRACION para ver la pagina publica ("/") con datos:
 * futbol y voleibol con partidos de ayer (jugados), de hoy y de mañana, y
 * dos pruebas de atletismo (una con podio, otra por correr).
 *
 * No esta en DatabaseSeeder: se corre a mano, despues de los seeders de
 * catalogo:  php artisan db:seed --class=DemoPublicSeeder
 *
 * - Solo en local/testing: nunca en produccion.
 * - No toca la olimpiada real: crea la suya (EDITION_NAME). Usa las 8
 *   primeras delegaciones activas del catalogo (o las crea si faltan) y
 *   agrega 6 participantes de prueba con DNI 9000000x.
 * - Idempotente: si la olimpiada de demostracion ya existe, no hace nada.
 * - Los resultados pasan por ResultService::close(), asi la tabla de
 *   posiciones y el puntaje general de la demo quedan coherentes.
 * - Para ocultarla de la pagina publica: desactivarla en /olimpiadas.
 */
class DemoPublicSeeder extends Seeder
{
    public const EDITION_NAME = 'Olimpiada de demostración';

    private const RUNNERS = [
        ['90000001', 'Rubén', 'Quispe Mamani'],
        ['90000002', 'Edwin', 'Condori Apaza'],
        ['90000003', 'Wilber', 'Huanca Ticona'],
        ['90000004', 'Percy', 'Flores Cutipa'],
        ['90000005', 'Néstor', 'Chambi Larico'],
        ['90000006', 'Hugo', 'Pari Calsin'],
    ];

    private User $organizer;

    private Carbon $today;

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('DemoPublicSeeder crea datos de prueba: solo corre en local.');
        }

        if (GamesEdition::where('name', self::EDITION_NAME)->exists()) {
            $this->command?->info('La olimpiada de demostración ya existe: no se crea nada.');

            return;
        }

        $this->organizer = User::role('organizador')->orderBy('id')->first()
            ?? User::role('admin')->orderBy('id')->first()
            ?? throw new RuntimeException('Falta un usuario organizador o admin: corre primero RolePermissionSeeder y AdminUserSeeder.');

        $this->today = now()->startOfDay();

        DB::transaction(function () {
            $venues = $this->venues();

            $edition = GamesEdition::create([
                'name' => self::EDITION_NAME,
                'organizer_id' => $this->organizer->id,
                'start_date' => $this->today->copy()->subDay(),
                'end_date' => $this->today->copy()->addDay(),
                'host_venue_id' => $venues[0]->id,
                'is_active' => true,
            ]);

            $teams = $this->teams();

            foreach ($teams as $team) {
                GamesEditionTeam::create(['games_edition_id' => $edition->id, 'team_id' => $team->id, 'status' => 'registered']);
            }

            $this->football($edition, $teams, $venues);
            $this->volleyball($edition, $teams->take(6)->values(), $venues);
            $this->athletics($edition, $teams->take(6)->values(), $venues);
        });

        $this->command?->info('Olimpiada de demostración creada. Ábrela en la página pública: '.route('home'));
    }

    /** @param  Collection<int, Team>  $teams  8 delegaciones */
    private function football(GamesEdition $edition, Collection $teams, Collection $venues): void
    {
        $season = $this->season($edition, 'Fútbol', 'Fútbol Libre Varones');
        $entries = $this->enroll($season, $teams);
        $stadium = $venues[0];

        // [dia, hora, local, visitante, resultado]: marcador [l, v], 'wo-away' o null (por jugar).
        $fixture = [
            [-1, '09:00', 0, 1, [2, 1]],
            [-1, '10:30', 2, 3, [0, 0]],
            [-1, '12:00', 4, 5, [3, 1]],
            [-1, '15:00', 6, 7, 'wo-away'],
            [0, '08:00', 0, 2, [1, 2]],
            [0, '09:30', 1, 3, [4, 0]],
            [0, '15:00', 4, 6, null],
            [0, '16:30', 5, 7, null],
            [1, '09:00', 0, 3, null],
            [1, '10:30', 1, 2, null],
        ];

        $this->play($season, $entries, $fixture, $stadium);
    }

    /** @param  Collection<int, Team>  $teams  6 delegaciones */
    private function volleyball(GamesEdition $edition, Collection $teams, Collection $venues): void
    {
        $season = $this->season($edition, 'Voleibol', 'Voleibol Libre Damas');
        $entries = $this->enroll($season, $teams);

        $fixture = [
            [-1, '09:00', 0, 1, [2, 0]],
            [-1, '10:00', 2, 3, [1, 2]],
            [-1, '11:00', 4, 5, [2, 1]],
            [0, '14:00', 0, 2, null],
            [0, '15:00', 1, 4, null],
            [0, '16:00', 3, 5, null],
            [1, '09:00', 0, 5, null],
            [1, '10:00', 2, 4, null],
        ];

        $this->play($season, $entries, $fixture, $venues[1] ?? $venues[0]);
    }

    /**
     * Dos pruebas individuales: 100 metros (corrida ayer, con marcas) y
     * 400 metros (mañana). Cada delegacion aporta un corredor.
     *
     * @param  Collection<int, Team>  $teams  6 delegaciones
     */
    private function athletics(GamesEdition $edition, Collection $teams, Collection $venues): void
    {
        $sport = $this->sport('Atletismo');
        $track = $venues[2] ?? $venues[0];
        $marks = [12.48, 12.91, 12.35, 13.20, 12.77, 13.04];

        foreach ([['100 metros', -1, '16:00', $marks], ['400 metros', 1, '11:00', null]] as [$name, $offset, $time, $results]) {
            $discipline = Discipline::where('sport_id', $sport->id)->where('name', $name)->first()
                ?? throw new RuntimeException("Falta la disciplina «{$name}» de Atletismo: corre primero DisciplineSeeder.");

            $season = $this->season($edition, 'Atletismo', "Atletismo {$name} Varones", $discipline);
            $entries = $this->enroll($season, $teams);
            $at = $this->today->copy()->addDays($offset)->setTimeFromTimeString($time);

            $match = GameMatch::create([
                'season_id' => $season->id,
                'matchday_id' => $this->matchday($season, $at)->id,
                'discipline_id' => $discipline->id,
                'venue_id' => $track->id,
                'round_type' => 'final',
                'status' => 'pending',
                'scheduled_at' => $at,
            ]);

            $lanes = [];

            foreach ($entries as $i => $entry) {
                [$dni, $first, $last] = self::RUNNERS[$i];

                $player = Player::firstOrCreate(['dni' => $dni], [
                    'first_name' => $first, 'last_name' => $last, 'birth_date' => '1988-05-14', 'gender' => 'M',
                    'staff_type' => 'teacher', 'employment_type' => 'appointed', 'is_active' => true,
                ]);

                $runner = SeasonTeamPlayer::create([
                    'season_team_id' => $entry->id, 'player_id' => $player->id, 'status' => 'active', 'enrolled_at' => $this->today->copy()->subDays(7),
                ]);

                $lanes[$i] = EventParticipant::create([
                    'match_id' => $match->id, 'participant_type' => 'season_team_player', 'participant_id' => $runner->id, 'lane_or_board' => $i + 1,
                ]);
            }

            if ($results) {
                app(ResultService::class)->close($match, [
                    'results' => collect($lanes)->mapWithKeys(fn (EventParticipant $p, int $i) => [$p->id => $results[$i]])->all(),
                ], $this->organizer);
            }
        }
    }

    /**
     * Crea los partidos del fixture y cierra los que traen resultado.
     *
     * @param  Collection<int, SeasonTeam>  $entries
     * @param  list<array{0: int, 1: string, 2: int, 3: int, 4: array{0: int, 1: int}|string|null}>  $fixture
     */
    private function play(Season $season, Collection $entries, array $fixture, Venue $venue): void
    {
        foreach ($fixture as [$offset, $time, $home, $away, $result]) {
            $at = $this->today->copy()->addDays($offset)->setTimeFromTimeString($time);

            $match = GameMatch::create([
                'season_id' => $season->id,
                'matchday_id' => $this->matchday($season, $at)->id,
                'venue_id' => $venue->id,
                'round_type' => 'regular',
                'status' => 'pending',
                'scheduled_at' => $at,
            ]);

            foreach (['home' => $home, 'away' => $away] as $side => $index) {
                EventParticipant::create([
                    'match_id' => $match->id, 'participant_type' => 'season_team', 'participant_id' => $entries[$index]->id, 'side' => $side,
                ]);
            }

            if ($result !== null) {
                app(ResultService::class)->close(
                    $match,
                    is_array($result) ? ['home' => $result[0], 'away' => $result[1]] : ['walkover' => 'away'],
                    $this->organizer,
                );
            }
        }
    }

    private function season(GamesEdition $edition, string $sportName, string $name, ?Discipline $discipline = null): Season
    {
        $season = Season::create([
            'games_edition_id' => $edition->id,
            'sport_id' => $this->sport($sportName)->id,
            'discipline_id' => $discipline?->id,
            'name' => $name,
            'start_date' => $edition->start_date,
            'end_date' => $edition->end_date,
            'status' => SeasonStatus::Active,
            'is_active' => true,
        ]);

        ScoringConfig::create(['season_id' => $season->id, 'phase_name' => 'Fase única']);

        return $season;
    }

    /** @return Collection<int, SeasonTeam> */
    private function enroll(Season $season, Collection $teams): Collection
    {
        return $teams->map(fn (Team $team) => SeasonTeam::create(['season_id' => $season->id, 'team_id' => $team->id, 'is_active' => true]))->values();
    }

    /** Una jornada por dia de competencia. */
    private function matchday(Season $season, Carbon $at): Matchday
    {
        $day = $at->toDateString();

        return Matchday::firstOrCreate(
            ['season_id' => $season->id, 'start_date' => $day],
            ['name' => 'Fecha '.(Matchday::where('season_id', $season->id)->count() + 1), 'end_date' => $day],
        );
    }

    private function sport(string $name): Sport
    {
        return Sport::where('name', $name)->first()
            ?? throw new RuntimeException("Falta el deporte «{$name}»: corre primero SportSeeder.");
    }

    /** @return Collection<int, Team> 8 delegaciones: las del catalogo y, si no alcanzan, de prueba. */
    private function teams(): Collection
    {
        $teams = Team::where('is_active', true)->orderBy('name')->limit(8)->get();

        for ($i = $teams->count() + 1; $i <= 8; $i++) {
            $teams->push(Team::firstOrCreate(['name' => "Delegación de prueba {$i}"], ['is_active' => true]));
        }

        return $teams->values();
    }

    /** @return Collection<int, Venue> al menos una sede */
    private function venues(): Collection
    {
        $venues = Venue::where('is_active', true)->orderBy('id')->limit(3)->get();

        return $venues->isNotEmpty()
            ? $venues
            : collect([Venue::create(['name' => 'Estadio de prueba', 'is_active' => true])]);
    }
}
