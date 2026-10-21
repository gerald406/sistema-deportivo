{{-- Sede de un partido o prueba con sus enlaces "Cómo llegar" (Google Maps en modo
     ruta) y OpenStreetMap: App\Support\VenueMap. Sin sede, "Sede por confirmar". --}}
@props(['venue'])

<div {{ $attributes->class('text-[color:var(--muted)]') }}>
    <p>
        <i class="fa-solid fa-location-dot mr-1 text-xs" aria-hidden="true"></i>{{ $venue?->name ?? 'Sede por confirmar' }}
    </p>
    @if ($venue)
        <p class="mt-1 flex flex-wrap items-center gap-3 text-xs">
            <a href="{{ \App\Support\VenueMap::directions($venue) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1 font-semibold text-[color:var(--lake)] hover:underline">
                <i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i> Cómo llegar
            </a>
            <a href="{{ \App\Support\VenueMap::openStreetMap($venue) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1 hover:text-[color:var(--lake)] hover:underline">
                <i class="fa-solid fa-map" aria-hidden="true"></i> OpenStreetMap
            </a>
        </p>
    @endif
</div>
