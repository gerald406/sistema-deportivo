<?php

declare(strict_types=1);

namespace App\Services;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de un listado como .xlsx (boton "Excel" de cada pantalla, ver
 * App\Livewire\Concerns\ExportsExcel). Solo da formato: QUE filas se
 * exportan lo decide cada componente con la misma consulta acotada por
 * rol que usa su render().
 *
 * Los textos se escriben como cadenas, nunca como formulas: un nombre que
 * empiece con "=" o "@" no se ejecuta al abrir el archivo.
 */
class ExcelExportService
{
    /**
     * @param  array<string, \Closure>  $columns  encabezado => fn ($fila) valor de la celda
     * @param  iterable<mixed>  $rows
     */
    public function download(string $title, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(
            fn () => $this->write('php://output', $title, $columns, $rows),
            $this->fileName($title),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @param  array<string, \Closure>  $columns
     * @param  iterable<mixed>  $rows
     */
    public function write(string $path, string $title, array $columns, iterable $rows): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($this->sheetName($title));

        $writer->addRow(Row::fromValues(array_keys($columns), (new Style)->setFontBold()));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(
                fn (\Closure $value) => $this->cell($value($row)),
                array_values($columns),
            )));
        }

        $writer->close();
    }

    public function fileName(string $title): string
    {
        return Str::slug($title).'-'.now()->format('Y-m-d_His').'.xlsx';
    }

    /** Excel no admite \ / ? * : [ ] en el nombre de la hoja ni mas de 31 caracteres. */
    private function sheetName(string $title): string
    {
        $name = trim(str_replace(['\\', '/', '?', '*', ':', '[', ']'], ' ', $title), " '");

        return mb_substr($name !== '' ? $name : 'Datos', 0, 31);
    }

    private function cell(mixed $value): string|int|float
    {
        return match (true) {
            $value === null => '',
            is_int($value), is_float($value) => $value,
            is_bool($value) => $value ? 'Sí' : 'No',
            $value instanceof DateTimeInterface => $value->format('d/m/Y'),
            $value instanceof BackedEnum => (string) $value->value,
            default => (string) $value,
        };
    }
}
