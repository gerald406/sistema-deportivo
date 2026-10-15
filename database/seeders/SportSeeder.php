<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SportFormatType;
use App\Enums\StandardStages;
use App\Models\Sport;
use Illuminate\Database\Seeder;

/**
 * Catalogo de las bases de Puno 2026 (docs/fases/00-especificacion.md §5.4):
 * 9 deportes y 3 actividades. Las actividades (puntualidad, estudiantina,
 * danza) se cargan como deporte de ranking para poder tener competencia y
 * podio como cualquier otro (S6).
 *
 * El slug es la identidad: lo usan los demas seeders. `config` y las
 * fases estandar solo se escriben al crear, para no pisar lo que el admin
 * haya ajustado. Ajedrez: series por sistema suizo fuera del sistema y
 * final de podio (D18).
 */
class SportSeeder extends Seeder
{
    public function run(): void
    {
        $h2h = SportFormatType::HeadToHead;
        $ranked = SportFormatType::IndividualRanked;

        $sports = [
            'futbol' => ['Fútbol', $h2h, ['max_players_court' => 11, 'max_players_team' => 18]],
            'futsal' => ['Futsal', $h2h, ['max_players_court' => 5, 'max_players_team' => 10]],
            'basquetbol' => ['Básquetbol', $h2h, ['max_players_court' => 5, 'max_players_team' => 10]],
            'voley' => ['Voleibol', $h2h, ['max_players_court' => 6, 'max_players_team' => 12, 'sets_to_win' => 3]],
            'ajedrez' => ['Ajedrez', $h2h, ['allows_draw' => true]],
            'tenis-de-mesa' => ['Tenis de mesa', $h2h, null],
            'tiro-al-sapo' => ['Tiro al sapo', $ranked, null],
            'ciclismo' => ['Ciclismo', $ranked, null],
            // Atletismo es individual_ranked; la Posta 4x100 (DisciplineSeeder)
            // sobrescribe el formato a team_relay solo para esa prueba.
            'atletismo' => ['Atletismo', $ranked, null],
            'puntualidad' => ['Puntualidad y presentación', $ranked, null],
            'estudiantina' => ['Estudiantina', $ranked, null],
            'danza' => ['Danza', $ranked, null],
        ];

        $standardStages = ['ajedrez' => StandardStages::PodiumSeries];

        foreach ($sports as $slug => [$name, $format, $config]) {
            $sport = Sport::firstOrNew(['slug' => $slug]);

            if (! $sport->exists) {
                $sport->config = $config;
                $sport->standard_stages = $standardStages[$slug] ?? null;
            }

            $sport->fill(['name' => $name, 'format_type' => $format, 'is_active' => true])->save();
        }
    }
}
