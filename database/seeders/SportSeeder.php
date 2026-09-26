<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SportFormatType;
use App\Models\Sport;
use Illuminate\Database\Seeder;

class SportSeeder extends Seeder
{
    public function run(): void
    {
        Sport::updateOrCreate(
            ['slug' => 'futbol'],
            [
                'name' => 'Futbol',
                'format_type' => SportFormatType::HeadToHead,
                'config' => ['max_players_court' => 11, 'max_players_team' => 22],
                'is_active' => true,
            ]
        );

        Sport::updateOrCreate(
            ['slug' => 'voley'],
            [
                'name' => 'Voley',
                'format_type' => SportFormatType::HeadToHead,
                'config' => ['max_players_court' => 6, 'max_players_team' => 12, 'sets_to_win' => 3],
                'is_active' => true,
            ]
        );

        // Atletismo es individual_ranked por defecto; la Posta 4x100 (ver
        // DisciplineSeeder) sobrescribe ese formato a team_relay solo para
        // esa disciplina.
        Sport::updateOrCreate(
            ['slug' => 'atletismo'],
            [
                'name' => 'Atletismo',
                'format_type' => SportFormatType::IndividualRanked,
                'config' => null,
                'is_active' => true,
            ]
        );

        Sport::updateOrCreate(
            ['slug' => 'ajedrez'],
            [
                'name' => 'Ajedrez',
                'format_type' => SportFormatType::HeadToHead,
                'config' => ['allows_draw' => true],
                'is_active' => true,
            ]
        );
    }
}
