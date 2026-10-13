<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Services\ExcelExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Boton "Excel" de un listado (<x-export-button />): descarga TODAS las
 * filas que cumplen los filtros y el orden actuales, no solo la pagina
 * visible.
 *
 * export() es un metodo publico, invocable desde la consola del
 * navegador: por eso el componente debe
 *  - autorizar en authorizeExport() igual que en mount() (el middleware
 *    de la ruta no se reaplica en /livewire/update), y
 *  - devolver en exportRows() la MISMA consulta acotada por rol que usa
 *    render() (un solo metodo para ambos), nunca una consulta aparte.
 */
trait ExportsExcel
{
    /** Titulo del archivo y de la hoja. */
    abstract protected function exportTitle(): string;

    /**
     * Mismas columnas que la tabla, en texto plano y sin la de acciones.
     *
     * @return array<string, \Closure> encabezado => fn ($fila) valor
     */
    abstract protected function exportColumns(): array;

    /** @return iterable<mixed> */
    abstract protected function exportRows(): iterable;

    abstract protected function authorizeExport(): void;

    public function export(ExcelExportService $excel): StreamedResponse
    {
        $this->authorizeExport();

        return $excel->download($this->exportTitle(), $this->exportColumns(), $this->exportRows());
    }
}
