{{-- Ficha de filtro de la pagina publica: elegida = tinta llena. El clic
     (wire:click) lo pone quien la usa. --}}
@props(['active' => false])

<button type="button" aria-pressed="{{ $active ? 'true' : 'false' }}"
        {{ $attributes->class([
            'inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full border px-3 text-sm font-semibold lg:h-9',
            'border-[color:var(--ink)] bg-[color:var(--ink)] text-white' => $active,
            'border-[color:var(--rule)] bg-white hover:border-[color:var(--lake)] hover:text-[color:var(--lake)]' => ! $active,
        ]) }}>
    {{ $slot }}
</button>
