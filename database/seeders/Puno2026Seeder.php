<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EditionTeamStatus;
use App\Enums\GenderScope;
use App\Models\Category;
use App\Models\GamesEdition;
use App\Models\GamesEditionEvent;
use App\Models\GamesEditionGroup;
use App\Models\GamesEditionTeam;
use App\Models\Sport;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Datos iniciales de los XXVIII Juegos de Puno 2026, tal como estan en las
 * bases (docs/fases/00-especificacion.md §5): olimpiada, 8 sedes, 4 series,
 * 18 delegaciones y las 26 filas del cuadro de puntaje.
 *
 * No esta en DatabaseSeeder: se corre a mano, despues de los seeders de
 * catalogo:  php artisan db:seed --class=Puno2026Seeder
 *
 * Idempotente y NO destructivo: solo crea lo que falta (firstOrCreate). Lo
 * que el admin o el organizador ya cambiaron (puntos, serie de una
 * delegacion, organizador) no se pisa al volver a correrlo.
 */
class Puno2026Seeder extends Seeder
{
    public const EDITION_NAME = 'XXVIII Juegos Deportivos Regionales de Docentes y Administrativos - Puno 2026';

    private const VENUES = [
        'Plaza de Armas de Yunguyo',
        'Estadio José Carlos Mariátegui',
        'Complejo Deportivo',
        'Coliseo Cerrado de Yunguyo',
        'Complejo Deportivo Agua Dulce',
        'Ruta C.P. Chimbo – Yunguyo',
        'Teatro Municipal de Yunguyo',
        'Coliseo Municipal de Yunguyo',
    ];

    /** Bases, "Conformacion de los grupos". Cada casillero es UNA delegacion (D5). */
    private const GROUPS = [
        'Serie A' => ['JAE Puno', 'Ilave', 'ESFA Pilcuyo-Moho', 'Santa Rosa Ñuñoa', 'Crucero Macusani'],
        'Serie B' => ['MNB Juliaca', 'Pedro Vilcapaza', 'ESFA Juliaca', 'Yunguyo Capachica'],
        'Serie C' => ['IESP Juli', 'Ayaviri', 'Muñani JDCH', 'Santa Lucía Putina', 'San Juan del Oro Mañazo'],
        'Serie D' => ['ESFA Puno', 'Huancané', 'Desaguadero', 'Ácora Zepita'],
    ];

    /** Topes de plantel de las bases por deporte: [minimo, maximo]. Vacio = sin restriccion. */
    private const ROSTER_LIMITS = [
        'futbol' => [7, 18],
        'futsal' => [3, 10],
        'basquetbol' => [null, 10],
        'voley' => [6, 12],
        'tiro-al-sapo' => [null, 3],
        'ajedrez' => [null, 1],
        'tenis-de-mesa' => [null, 1],
        'ciclismo' => [null, 1],
        'estudiantina' => [10, null],
        // Atletismo: el tope depende de la prueba (1 por prueba, 4 en la posta).
        // Danza: las bases dicen 8 y 12 parejas (C4). Quedan vacios.
    ];

    /**
     * Cuadro "Puntaje total por categorias" (pag. 9), literal:
     * [etiqueta, deporte, nivel, alcance, 1.o, 2.o, 3.o].
     * Las filas 23-26 las anuncia el Art. 1 pero el cuadro no las puntua:
     * quedan sin puntos hasta que la comision decida (D6).
     */
    private const SCORING_TABLE = [
        1 => ['Puntualidad y presentación', 'puntualidad', null, 'none', 18, 14, 10],
        2 => ['Ajedrez Libre', 'ajedrez', 'Libre', 'both', 8, 6, 4],
        3 => ['Ajedrez Máster', 'ajedrez', 'Máster', 'both', 8, 6, 4],
        4 => ['Ajedrez Súper Máster', 'ajedrez', 'Súper Máster', 'both', 8, 6, 4],
        5 => ['Atletismo Libre', 'atletismo', 'Libre', 'both', 6, 4, 2],
        6 => ['Atletismo Máster', 'atletismo', 'Máster', 'both', 6, 4, 2],
        7 => ['Atletismo Súper Máster', 'atletismo', 'Súper Máster', 'both', 6, 4, 2],
        8 => ['Básquetbol Libre', 'basquetbol', 'Libre', 'both', 20, 16, 12],
        9 => ['Básquetbol Máster', 'basquetbol', 'Máster', 'both', 20, 16, 12],
        10 => ['Ciclismo Libre', 'ciclismo', 'Libre', 'male', 20, 16, 12],
        11 => ['Fútbol Libre', 'futbol', 'Libre', 'male', 24, 20, 16],
        12 => ['Futsal Libre', 'futsal', 'Libre', 'both', 20, 16, 12],
        13 => ['Futsal Máster', 'futsal', 'Máster', 'male', 20, 16, 12],
        14 => ['Futsal Súper Máster', 'futsal', 'Súper Máster', 'male', 28, 24, 20],
        15 => ['Sapo Libre', 'tiro-al-sapo', 'Libre', 'both', 10, 8, 6],
        16 => ['Sapo Máster', 'tiro-al-sapo', 'Máster', 'both', 8, 6, 4],
        17 => ['Sapo Súper Máster', 'tiro-al-sapo', 'Súper Máster', 'both', 10, 8, 6],
        18 => ['Tenis de mesa Libre', 'tenis-de-mesa', 'Libre', 'both', 6, 4, 2],
        19 => ['Tenis de mesa Máster', 'tenis-de-mesa', 'Máster', 'both', 8, 6, 4],
        20 => ['Tenis de mesa Súper Máster', 'tenis-de-mesa', 'Súper Máster', 'both', 24, 20, 16],
        21 => ['Voleibol Libre', 'voley', 'Libre', 'both', 20, 16, 12],
        22 => ['Voleibol Máster', 'voley', 'Máster', 'both', 24, 20, 16],
        23 => ['Ciclismo Libre damas', 'ciclismo', 'Libre', 'female', null, null, null],
        24 => ['Futsal Máster damas', 'futsal', 'Máster', 'female', null, null, null],
        25 => ['Estudiantina', 'estudiantina', 'Libre', 'none', null, null, null],
        26 => ['Danza', 'danza', 'Libre', 'none', null, null, null],
    ];

    public function run(): void
    {
        $organizer = $this->organizer();

        foreach (self::VENUES as $name) {
            Venue::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        $edition = GamesEdition::firstOrCreate(
            ['name' => self::EDITION_NAME],
            [
                'organizer_id' => $organizer->id,
                'start_date' => '2026-10-28',
                'end_date' => '2026-10-30',
                'is_active' => true,
            ]
        );

        $this->seedGroupsAndDelegations($edition);
        $this->seedScoringTable($edition);
    }

    /** El primer organizador; si aun no hay ninguno, el admin (que despues lo reasigna). */
    private function organizer(): User
    {
        return User::role('organizador')->orderBy('id')->first()
            ?? User::role('admin')->orderBy('id')->first()
            ?? throw new RuntimeException('Corre primero DatabaseSeeder: no hay ningún organizador ni administrador.');
    }

    private function seedGroupsAndDelegations(GamesEdition $edition): void
    {
        $order = 0;

        foreach (self::GROUPS as $groupName => $delegations) {
            $group = GamesEditionGroup::firstOrCreate(
                ['games_edition_id' => $edition->id, 'name' => $groupName],
                ['sort_order' => ++$order]
            );

            foreach ($delegations as $name) {
                // created_by: asi el organizador de la olimpiada la ve en /equipos.
                $team = Team::firstOrCreate(['name' => $name], ['is_active' => true, 'created_by' => $edition->organizer_id]);

                GamesEditionTeam::firstOrCreate(
                    ['games_edition_id' => $edition->id, 'team_id' => $team->id],
                    ['group_id' => $group->id, 'status' => EditionTeamStatus::Registered]
                );
            }
        }
    }

    private function seedScoringTable(GamesEdition $edition): void
    {
        $sports = Sport::pluck('id', 'slug');
        $levels = Category::where('is_active', true)->pluck('id', 'name');

        foreach (self::SCORING_TABLE as $number => [$label, $slug, $level, $scope, $first, $second, $third]) {
            if (! isset($sports[$slug]) || ($level !== null && ! isset($levels[$level]))) {
                throw new RuntimeException("Corre primero los seeders de catálogo: falta el deporte «{$slug}» o el nivel «{$level}».");
            }

            [$min, $max] = self::ROSTER_LIMITS[$slug] ?? [null, null];

            GamesEditionEvent::firstOrCreate(
                ['games_edition_id' => $edition->id, 'label' => $label],
                [
                    'sort_order' => $number,
                    'sport_id' => $sports[$slug],
                    'category_id' => $level !== null ? $levels[$level] : null,
                    'gender_scope' => GenderScope::from($scope),
                    // S1: "(damas y varones)" = un podio por genero, con los mismos puntos.
                    'separate_podiums' => true,
                    // S2: cada prueba de atletismo da los puntos de la fila.
                    'per_discipline' => $slug === 'atletismo',
                    'pts_first' => $first,
                    'pts_second' => $second,
                    'pts_third' => $third,
                    'min_players' => $min,
                    'max_players' => $max,
                    'rules_blocking' => false,
                    'is_active' => true,
                ]
            );
        }
    }
}
