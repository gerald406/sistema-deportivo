<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StageMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Fase de una competencia: una serie, la fase final o una final de podio directo. */
class SeasonStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'name',
        'sort_order',
        'mode',
        'group_id',
        'qualifiers',
        'has_third_place',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'mode' => StageMode::class,
            'qualifiers' => 'integer',
            'has_third_place' => 'boolean',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** Serie de la olimpiada a la que corresponde la fase (si es una serie). */
    public function group(): BelongsTo
    {
        return $this->belongsTo(GamesEditionGroup::class, 'group_id');
    }

    /** Podio directo, ordenado por puesto. */
    public function results(): HasMany
    {
        return $this->hasMany(SeasonStageResult::class)->orderBy('place')->orderBy('id');
    }
}
