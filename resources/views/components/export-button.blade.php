{{-- Descarga el listado filtrado como .xlsx (trait ExportsExcel del componente). --}}
<button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export"
        title="Exportar a Excel las filas que cumplen los filtros"
        {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-emerald-600 bg-white px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 disabled:cursor-wait disabled:opacity-60']) }}>
    <i class="fa-solid fa-file-excel" wire:loading.remove wire:target="export"></i>
    <i class="fa-solid fa-spinner fa-spin" wire:loading wire:target="export"></i>
    Excel
</button>
