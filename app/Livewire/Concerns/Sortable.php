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
 *
 * $sortField/$sortDirection son propiedades publicas: desde la consola
 * del navegador se pueden cambiar con $wire.set(...) sin pasar por
 * sortBy(). Por eso render() NUNCA debe usarlas directo en orderBy():
 * usar $this->sortColumn() / $this->sortOrder(), que validan contra la
 * lista blanca de sortableFields().
 */
trait Sortable
{
    /**
     * Columnas por las que se permite ordenar (las mismas que usan los
     * <x-sortable-th> de la vista). La primera es el fallback si llega
     * un valor no permitido.
     *
     * @return array<int, string>
     */
    abstract protected function sortableFields(): array;

    public function sortBy(string $field): void
    {
        if (! in_array($field, $this->sortableFields(), true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    protected function sortColumn(): string
    {
        $fields = $this->sortableFields();

        return in_array($this->sortField, $fields, true) ? $this->sortField : $fields[0];
    }

    protected function sortOrder(): string
    {
        return $this->sortDirection === 'desc' ? 'desc' : 'asc';
    }
}
