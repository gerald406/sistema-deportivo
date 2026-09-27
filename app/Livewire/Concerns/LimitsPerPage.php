<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * $perPage es una propiedad publica: desde la consola del navegador se
 * puede poner $wire.set('perPage', 1000000) y forzar una consulta
 * enorme. render() debe paginar con $this->perPageLimit(), que solo
 * acepta los valores del selector de la vista.
 *
 * Igual que Sortable, el trait no declara $perPage: cada componente la
 * declara con su default.
 */
trait LimitsPerPage
{
    /** @var array<int, int> Mismas opciones que el <select> de la vista. */
    protected array $perPageOptions = [10, 15, 25, 50];

    protected function perPageLimit(): int
    {
        return in_array($this->perPage, $this->perPageOptions, true) ? $this->perPage : 15;
    }
}
