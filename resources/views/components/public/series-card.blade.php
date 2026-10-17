{{-- Tarjeta de una serie en la portada publica. La letra grande es la serie;
     "Finales y podios" (sin letra) lleva el trofeo. Toda la tarjeta es el enlace. --}}
@props(['card', 'href', 'wide' => false])

@php
    $today = now()->startOfDay();
    $next = $card['nextAt'];
    $nextLabel = match (true) {
        $next === null => null,
        $next->isSameDay($today) => 'Hoy, '.$next->format('H:i'),
        $next->isSameDay($today->copy()->addDay()) => 'Mañana, '.$next->format('H:i'),
        default => $next->locale('es')->isoFormat('ddd D [de] MMM, HH:mm'),
    };
    $teams = $card['teams'];
@endphp

<a href="{{ $href }}"
   wire:click.prevent="openSeries('{{ $card['key'] }}')"
   x-on:click="window.scrollTo({ top: 0 })"
   {{ $attributes->class([
       'series-card group grid min-w-0 overflow-hidden rounded-lg border border-[color:var(--rule)] bg-white',
       'grid-cols-[4.5rem_minmax(0,1fr)] sm:grid-cols-[6.5rem_minmax(0,1fr)]',
       'md:col-span-2' => $wide,
   ]) }}>
    <span @class([
        'series-mark flex items-center justify-center text-white',
        'bg-[color:var(--lake)]' => $card['mark'] !== null,
        'bg-[color:var(--ink)]' => $card['mark'] === null,
    ]) aria-hidden="true">
        @if ($card['mark'] !== null)
            <span class="narrow text-6xl font-extrabold leading-none sm:text-8xl">{{ $card['mark'] }}</span>
        @else
            <i class="fa-solid fa-trophy text-4xl text-[color:var(--signal)] sm:text-5xl"></i>
        @endif
    </span>

    <span class="flex min-w-0 flex-col gap-3 p-4 sm:p-5">
        <span class="flex items-start justify-between gap-3">
            <span class="min-w-0">
                <span class="narrow block text-2xl font-extrabold leading-tight sm:text-3xl">{{ $card['name'] }}</span>
                <span class="mt-1 block text-sm text-[color:var(--muted)]">
                    @if ($card['mark'] !== null)
                        {{ $teams->count() }} {{ $teams->count() === 1 ? 'delegación' : 'delegaciones' }}
                    @else
                        Fase final de cada deporte, finales de podio y pruebas sin serie
                    @endif
                </span>
            </span>
            @if ($card['today'] > 0)
                <span class="figures shrink-0 rounded bg-[color:var(--signal)] px-2 py-0.5 text-xs font-bold text-[color:var(--ink)]">
                    Hoy, {{ $card['today'] }} {{ $card['today'] === 1 ? 'partido' : 'partidos' }}
                </span>
            @endif
        </span>

        @if ($teams->isNotEmpty())
            <span class="block text-sm leading-relaxed">{{ \Illuminate\Support\Arr::join($teams->all(), ', ', ' y ') }}</span>
        @endif

        <span class="mt-auto grid grid-cols-3 gap-3 border-t border-[color:var(--rule)] pt-3">
            <span>
                <span class="block text-xs text-[color:var(--muted)]">Por jugar</span>
                <span class="narrow figures block text-2xl font-bold leading-tight">{{ $card['pending'] }}</span>
            </span>
            <span>
                <span class="block text-xs text-[color:var(--muted)]">Jugados</span>
                <span class="narrow figures block text-2xl font-bold leading-tight">{{ $card['played'] }}</span>
            </span>
            @if ($card['podiumStages'] > 0)
                <span>
                    <span class="block text-xs text-[color:var(--muted)]">Podios</span>
                    <span class="narrow figures block text-2xl font-bold leading-tight">
                        {{ $card['podiums'] }}<span class="text-base font-semibold text-[color:var(--muted)]"> de {{ $card['podiumStages'] }}</span>
                    </span>
                </span>
            @endif
        </span>

        <span class="flex items-center justify-between gap-3 text-sm">
            <span class="min-w-0 truncate">
                @if ($nextLabel)
                    <span class="text-[color:var(--muted)]">Próximo partido:</span>
                    <span class="font-semibold">{{ $nextLabel }}</span>
                @elseif ($card['pending'] > 0)
                    <span class="text-[color:var(--muted)]">Horarios por confirmar</span>
                @else
                    <span class="text-[color:var(--muted)]">Sin partidos por jugar</span>
                @endif
            </span>
            <span class="shrink-0 font-semibold text-[color:var(--lake)] group-hover:underline">Ver serie</span>
        </span>
    </span>
</a>
