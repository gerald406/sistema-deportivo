<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Ordenamiento de columnas (mayor a menor / A-Z) reutilizable entre
 * listados. El componente que lo usa debe traer WithPagination (sortBy()
 * reinicia la pagina) y DEBE declarar sus propias propiedades
 * $sortField/$sortDirection con el default que le convenga.
 *
 * A proposito el trait NO declara esas propiedades: si lo hiciera con un
 * valor por defecto, y la clase que lo usa las redeclara con un default
 * distinto (como aqui, donde cada listado ordena por una columna
 * distinta), PHP 8.2 lo trata como una redefinicion incompatible y
 * lanza un FatalError al cargar la clase ("... define the same
 * property... However, the definition differs and is incompatible").
 */
trait Sortable
{
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }
}
