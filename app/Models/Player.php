<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\StaffType;
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
        'gender',
        'staff_type',
        'employment_type',
        'photo_path',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
            'staff_type' => StaffType::class,
            'employment_type' => EmploymentType::class,
            // Fuera de $fillable a proposito: solo la fija
            // PlayerRegistrationService::setDocumentsVerified().
            'documents_verified_at' => 'datetime',
            // Tambien fuera de $fillable: solo los escribe
            // PlayerRegistrationService::attachResolution()/removeResolution().
            'resolution_uploaded_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** Quien subio la resolucion (PDF) vigente: evidencia para reclamos. */
    public function resolutionUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolution_uploaded_by');
    }

    public function seasonTeamPlayers(): HasMany
    {
        return $this->hasMany(SeasonTeamPlayer::class);
    }

    /**
     * Quien registro el participante en el catalogo global. Usado por
     * PlayerPolicy mientras el participante todavia no tiene ninguna
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
