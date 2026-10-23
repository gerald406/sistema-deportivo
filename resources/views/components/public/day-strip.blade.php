{{-- Tira de fechas de Programacion y Resultados: "Todas las fechas" y cada
     dia con cuanto tiene, en el orden del listado (de la fecha mas reciente
     a la mas antigua, sin fecha al final). "Hoy" va en naranja.
     $days sale de PublicResultsService::daysOf(); $active es la clave elegida o null. --}}
@props(['days', 'active' => null])

@php
    $today = now()->startOfDay();
    $base = 'flex h-14 shrink-0 flex-col items-start justify-center rounded-lg border px-3 text-left';
    $chosenClass = 'border-[color:var(--ink)] bg-[color:var(--ink)] text-white';
@endphp

@if ($days->count() > 1)
    <div role="group" aria-label="Fecha" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:px-0">
        <button type="button" wire:click="$set('date', '')" aria-pressed="{{ $active === null ? 'true' : 'false' }}"
                class="{{ $base }} {{ $active === null ? $chosenClass : 'border-[color:var(--rule)] bg-white hover:border-[color:var(--lake)]' }}">
            <span class="text-sm font-bold">Todas las fechas</span>
            <span class="figures text-xs {{ $active === null ? 'text-white/75' : 'text-[color:var(--muted)]' }}">{{ $days->sum('count') }}</span>
        </button>

        @foreach ($days as $day)
            @php
                $date = $day['date'];
                $chosen = $day['key'] === $active;
                $isToday = $date?->equalTo($today) ?? false;
                $hint = match (true) {
                    $date === null => null,
                    $isToday => 'Hoy',
                    $date->equalTo($today->copy()->addDay()) => 'Mañana',
                    $date->equalTo($today->copy()->subDay()) => 'Ayer',
                    default => null,
                };
            @endphp
            <button type="button" wire:key="day-chip-{{ $day['key'] }}" wire:click="$set('date', '{{ $day['key'] }}')"
                    aria-pressed="{{ $chosen ? 'true' : 'false' }}"
                    @class([
                        $base,
                        $chosenClass => $chosen,
                        'border-2 border-[color:var(--signal)] bg-white' => ! $chosen && $isToday,
                        'border-[color:var(--rule)] bg-white hover:border-[color:var(--lake)]' => ! $chosen && ! $isToday,
                    ])>
                <span class="whitespace-nowrap text-sm font-bold">
                    {{ $date ? ucfirst($date->locale('es')->isoFormat('ddd D [de] MMM')) : 'Por confirmar' }}
                </span>
                <span class="figures whitespace-nowrap text-xs {{ $chosen ? 'text-white/75' : 'text-[color:var(--muted)]' }}">
                    @if ($hint === 'Hoy')
                        <span class="rounded bg-[color:var(--signal)] px-1 font-bold text-[color:var(--ink)]">Hoy</span> ·
                    @elseif ($hint)
                        {{ $hint }} ·
                    @endif
                    {{ $day['count'] }}
                </span>
            </button>
        @endforeach
    </div>
@endif
