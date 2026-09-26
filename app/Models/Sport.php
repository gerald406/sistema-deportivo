<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SportFormatType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sport extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'format_type',
        'config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'format_type' => SportFormatType::class,
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function disciplines(): HasMany
    {
        return $this->hasMany(Discipline::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(SportPosition::class);
    }

    public function eventTypes(): HasMany
    {
        return $this->hasMany(EventType::class);
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }
}
