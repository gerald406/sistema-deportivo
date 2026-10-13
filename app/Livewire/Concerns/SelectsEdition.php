<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\GamesEdition;
use App\Services\GamesEditionService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Pantallas que trabajan dentro de UNA olimpiada (series, delegaciones
 * inscritas, cuadro de puntaje). La olimpiada va en la URL (?olimpiada=)
 * en vez de en la ruta para que la entrada del menu funcione sin
 * parametros: se preselecciona la olimpiada activa mas reciente que el
 * usuario gestiona.
 *
 * editionId es una propiedad publica (manipulable desde el navegador):
 * edition() SIEMPRE la busca dentro de las olimpiadas que el usuario
 * gestiona (GamesEditionService::scopeManagedBy()), nunca con find().
 */
trait SelectsEdition
{
    #[Url(as: 'olimpiada')]
    public string $editionId = '';

    public function mountSelectsEdition(): void
    {
        if ($this->edition() === null) {
            $default = $this->editionOptions()->first();
            $this->editionId = $default ? (string) $default->id : '';
        }
    }

    public function updatedEditionId(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /** Olimpiadas que el usuario gestiona: activas primero, luego las mas recientes. */
    public function editionOptions(): Collection
    {
        return app(GamesEditionService::class)
            ->scopeManagedBy(GamesEdition::query(), auth()->user())
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_active', 'organizer_id']);
    }

    public function edition(): ?GamesEdition
    {
        if (! ctype_digit($this->editionId)) {
            return null;
        }

        return app(GamesEditionService::class)
            ->scopeManagedBy(GamesEdition::query(), auth()->user())
            ->find((int) $this->editionId);
    }

    /** La olimpiada elegida o 404: para las acciones que la necesitan. */
    protected function editionOrFail(): GamesEdition
    {
        return $this->edition() ?? abort(404);
    }
}
