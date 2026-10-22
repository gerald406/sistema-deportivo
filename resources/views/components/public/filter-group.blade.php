{{-- Un grupo de fichas del panel de filtros (Serie, Deporte, Nivel...). --}}
@props(['label'])

<div role="group" aria-label="{{ $label }}" {{ $attributes }}>
    <p class="mb-2 text-xs font-bold uppercase tracking-wider text-[color:var(--muted)]">{{ $label }}</p>
    <div class="flex flex-wrap gap-1.5">
        {{ $slot }}
    </div>
</div>
