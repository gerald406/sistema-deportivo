{{-- Podio directo de una fase (1.º, 2.º y 3.º) con su estado: Definido o
     Programado (por disputar, con fecha o sede). Si el puesto es de un
     participante, su nombre va primero y la delegacion debajo.
     compact: con la prueba ya elegida en los filtros, el titulo es solo nivel
     y genero. "Mi delegacion" (mine, del x-data de la pagina) se resalta. --}}
@props(['stage', 'compact' => false])

@php
    $season = $stage->season;
    $decided = $stage->results->isNotEmpty();
    $short = collect([$season->category?->name, $season->gender?->label()])->filter()->join(' · ');
    $title = $compact && $short !== '' ? $short : $season->name;
@endphp

<article {{ $attributes->class('flex flex-col gap-3 rounded-lg border border-[color:var(--rule)] bg-white p-4') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h4 class="font-bold leading-snug">{{ $title }}</h4>
            <p class="text-sm text-[color:var(--muted)]">{{ $stage->name }}</p>
        </div>
        <span @class([
            'shrink-0 rounded px-2 py-0.5 text-xs font-bold',
            'bg-[#e3edf6] text-[color:var(--lake)]' => $decided,
            'bg-[#fff1e4] text-[#8a4200]' => ! $decided,
        ])>
            {{ $decided ? 'Definido' : 'Programado' }}
        </span>
    </div>

    @if ($stage->scheduled_at || $stage->venue)
        <div class="text-sm text-[color:var(--muted)]">
            <p>
                <i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>
                {{ $stage->scheduled_at ? ucfirst($stage->scheduled_at->locale('es')->isoFormat('dddd D [de] MMMM, HH:mm')) : 'Fecha por confirmar' }}
            </p>
            @if ($stage->venue)
                <x-public.venue :venue="$stage->venue" class="mt-1" />
            @endif
        </div>
    @endif

    @if (! $decided)
        <p class="text-sm font-medium">Por disputar</p>
    @else
        <ol class="space-y-1">
            @foreach ($stage->results as $result)
                @php $player = $result->seasonTeamPlayer?->player; @endphp
                <li class="-mx-2 grid grid-cols-[1.75rem_minmax(0,1fr)] items-start gap-3 rounded-md px-2 py-1"
                    x-bind:class="mine === {{ (int) $result->team_id }} ? 'bg-[color:var(--wash)]' : ''">
                    <span @class([
                        'figures mt-0.5 flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold',
                        'bg-[color:var(--gold)] text-[color:var(--ink)]' => $result->place === 1,
                        'bg-[color:var(--silver)] text-[color:var(--ink)]' => $result->place === 2,
                        'bg-[color:var(--bronze)] text-white' => $result->place === 3,
                    ])>
                        {{ $result->place }}<span class="sr-only">.º puesto</span>
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate {{ $result->place === 1 ? 'font-bold' : 'font-medium' }}">
                            {{ $player ? "{$player->last_name}, {$player->first_name}" : ($result->team?->name ?? '—') }}
                        </span>
                        @if ($player && $result->team)
                            <span class="block truncate text-sm text-[color:var(--muted)]">{{ $result->team->name }}</span>
                        @endif
                        <span x-show="mine === {{ (int) $result->team_id }}" x-cloak class="block text-xs font-bold text-[color:var(--lake)]">
                            <i class="fa-solid fa-star text-[color:var(--signal)]" aria-hidden="true"></i> Tu delegación
                        </span>
                    </span>
                </li>
            @endforeach
        </ol>
    @endif
</article>
