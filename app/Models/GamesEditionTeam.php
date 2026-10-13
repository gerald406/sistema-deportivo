<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EditionTeamStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Delegacion inscrita en una olimpiada. Unica fuente del delegado titular
 * y suplente (docs/fases/00-especificacion.md, D9): ambos tienen los
 * mismos permisos sobre los planteles de la delegacion en esa olimpiada.
 */
class GamesEditionTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'games_edition_id',
        'team_id',
        'group_id',
        'delegate_id',
        'substitute_delegate_id',
        'status',
        'fee_paid',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'status' => EditionTeamStatus::class,
            'fee_paid' => 'boolean',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(GamesEdition::class, 'games_edition_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GamesEditionGroup::class, 'group_id');
    }

    /** Delegado titular. */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    /** Delegado suplente (accesitario). */
    public function substituteDelegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_delegate_id');
    }

    /** Titular y suplente, sin nulos. */
    public function delegates(): Collection
    {
        return collect([$this->delegate, $this->substituteDelegate])->filter()->values();
    }

    public function hasDelegate(User $user): bool
    {
        return $this->delegate_id === $user->id || $this->substitute_delegate_id === $user->id;
    }
}
