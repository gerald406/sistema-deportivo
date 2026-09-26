<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BetterDirection;
use App\Enums\SportFormatType;
use App\Enums\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discipline extends Model
{
    use HasFactory;

    protected $fillable = [
        'sport_id',
        'name',
        'format_type',
        'unit_of_measure',
        'better_direction',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'format_type' => SportFormatType::class,
            'unit_of_measure' => UnitOfMeasure::class,
            'better_direction' => BetterDirection::class,
            'is_active' => 'boolean',
        ];
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }
}
