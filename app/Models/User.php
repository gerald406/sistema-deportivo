<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasRoles;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Torneos que organiza (tournaments.organizer_id, RESTRICT).
     */
    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'organizer_id');
    }

    /**
     * Equipos de los que es delegado general (teams.delegate_id, SET NULL).
     */
    public function delegatedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'delegate_id');
    }

    /**
     * Inscripciones de las que es delegado de temporada
     * (season_team.delegate_id, SET NULL).
     */
    public function delegatedSeasonTeams(): HasMany
    {
        return $this->hasMany(SeasonTeam::class, 'delegate_id');
    }

    /**
     * Jugadores que registro (players.created_by, SET NULL).
     */
    public function createdPlayers(): HasMany
    {
        return $this->hasMany(Player::class, 'created_by');
    }

    /**
     * Reaperturas de partidos que realizo (match_reopen_logs.user_id, RESTRICT).
     */
    public function matchReopenLogs(): HasMany
    {
        return $this->hasMany(MatchReopenLog::class);
    }
}
