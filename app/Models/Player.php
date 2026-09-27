<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'dni',
        'first_name',
        'last_name',
        'birth_date',
        'photo_path',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function seasonTeamPlayers(): HasMany
    {
        return $this->hasMany(SeasonTeamPlayer::class);
    }

    /**
     * Quien registro el jugador en el catalogo global. Usado por
     * PlayerPolicy mientras el jugador todavia no tiene ninguna
     * inscripcion (season_team_player) que determine a que equipo
     * pertenece.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
