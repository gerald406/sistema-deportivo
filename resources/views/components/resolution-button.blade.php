{{-- Abre la resolucion (PDF) del participante en el modal de <x-resolution-modal />.
     URL y nombre van en data-* (escapados por Blade), no interpolados en JavaScript. --}}
@props(['player'])

@if ($player->resolution_path)
    <button type="button"
            data-url="{{ route('players.resolution', $player) }}"
            data-name="{{ $player->fullName() }} · DNI {{ $player->dni }}"
            x-on:click="resolution = { url: $el.dataset.url, name: $el.dataset.name }"
            class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-700 hover:bg-sky-200">
        <i class="fa-solid fa-file-pdf"></i> Ver
    </button>
@else
    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
        <i class="fa-solid fa-triangle-exclamation"></i> Sin resolución
    </span>
@endif
