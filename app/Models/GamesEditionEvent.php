<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\GenderScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fila del cuadro de puntaje de una olimpiada: un deporte o actividad en
 * un nivel, con los puntos del podio y los topes de plantel.
 */
class GamesEditionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'games_edition_id',
        'sort_order',
        'label',
        'sport_id',
        'category_id',
        'gender_scope',
        'separate_podiums',
        'per_discipline',
        'pts_first',
        'pts_second',
        'pts_third',
        'min_players',
        'max_players',
        'rules_blocking',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'gender_scope' => GenderScope::class,
            'separate_podiums' => 'boolean',
            'per_discipline' => 'boolean',
            'pts_first' => 'integer',
            'pts_second' => 'integer',
            'pts_third' => 'integer',
            'min_players' => 'integer',
            'max_players' => 'integer',
            'rules_blocking' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(GamesEdition::class, 'games_edition_id');
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Competencias generadas desde esta fila. */
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class, 'games_edition_event_id');
    }

    /**
     * Generos para los que la fila genera podio (valores de Gender; null =
     * competencia sin genero). "Damas y varones" da dos podios o uno
     * combinado segun separate_podiums.
     *
     * @return list<string|null>
     */
    public function genders(): array
    {
        return match ($this->gender_scope) {
            GenderScope::Male => [Gender::Male->value],
            GenderScope::Female => [Gender::Female->value],
            GenderScope::Both => $this->separate_podiums
                ? [Gender::Male->value, Gender::Female->value]
                : [Gender::Mixed->value],
            GenderScope::None => [null],
        };
    }

    /** Puntos que da el puesto al puntaje general; 0 si no esta definido. */
    public function pointsFor(int $place): int
    {
        return (int) match ($place) {
            1 => $this->pts_first,
            2 => $this->pts_second,
            3 => $this->pts_third,
            default => 0,
        };
    }
}
