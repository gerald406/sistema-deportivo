{{-- Un partido o prueba de la pagina publica: hora, contendientes con marcador y,
     a la derecha, serie, competencia, ronda y sede. $game sale de
     SeasonOverviewService::matchRow() o stageRow(); $series es ['mark', 'name']
     de su serie (mark null = Finales) o null si la olimpiada no tiene series.
     Si juega "Mi delegacion" (mine, del x-data de la pagina) la fila se resalta. --}}
@props(['game', 'series' => null])

@php
    $decided = $game['status'] !== \App\Enums\MatchStatus::Pending;
    $walkover = $game['status'] === \App\Enums\MatchStatus::Walkover;
    $venue = $game['venue'];
    $mineCheck = 'mine && '.\Illuminate\Support\Js::from($game['teamIds'] ?? []).'.includes(mine)';
@endphp

<li x-bind:class="{{ $mineCheck }} ? 'bg-[color:var(--wash)]' : ''"
    {{ $attributes->class('-mx-4 grid grid-cols-[3.75rem_minmax(0,1fr)] gap-x-4 gap-y-2 border-b border-[color:var(--rule)] px-4 py-4 md:grid-cols-[4.5rem_minmax(0,1fr)_minmax(0,17rem)] lg:mx-0 lg:px-3') }}>

    {{-- Hora --}}
    <div class="narrow figures text-2xl font-bold leading-none sm:text-3xl">
        @if ($game['scheduledAt'])
            {{ $game['scheduledAt']->format('H:i') }}
        @else
            <span class="text-base font-semibold text-[color:var(--muted)]">Hora por confirmar</span>
        @endif
    </div>

    {{-- Contendientes y marcador --}}
    <div class="min-w-0">
        @if ($game['isHeadToHead'])
            @foreach (['home', 'away'] as $side)
                @php
                    $won = $decided && $game['winnerSide'] === $side;
                    $lost = $decided && $game['winnerSide'] !== null && ! $won;
                @endphp
                <div class="flex items-baseline justify-between gap-3 {{ $loop->first ? '' : 'mt-1' }}">
                    <span class="truncate text-base sm:text-lg {{ $won ? 'font-bold' : ($lost ? 'text-[color:var(--muted)]' : 'font-medium') }}">
                        {{ $game[$side] }}
                        @if ($walkover && $lost)
                            <span class="ml-1 text-sm font-normal">no se presentó</span>
                        @endif
                    </span>
                    @if ($decided && ! $walkover)
                        <span class="narrow figures flex shrink-0 items-baseline gap-1.5 text-2xl leading-none {{ $won ? 'font-extrabold' : ($lost ? 'font-semibold text-[color:var(--muted)]' : 'font-semibold') }}">
                            {{ $game[$side.'Score'] ?? '–' }}
                            {{-- Marca del ganador: ocupa su ancho en ambas filas para que los marcadores queden alineados. --}}
                            <i class="fa-solid fa-caret-left w-2 text-sm {{ $won ? 'text-[color:var(--signal)]' : 'invisible' }}" aria-hidden="true"></i>
                        </span>
                    @endif
                </div>
            @endforeach

            @if ($walkover)
                <p class="mt-1.5 text-sm font-semibold">Ganó por W.O.</p>
            @elseif ($decided && $game['tiebreak'])
                <p class="mt-1.5 text-sm text-[color:var(--muted)]">Se definió por {{ mb_strtolower($game['tiebreak']) }}</p>
            @elseif ($decided && $game['winnerSide'] === null)
                <p class="mt-1.5 text-sm text-[color:var(--muted)]">Empate</p>
            @endif
        @else
            {{-- Prueba individual o posta: no hay local ni visitante. --}}
            <p class="text-base font-bold sm:text-lg">{{ $game['event'] }}</p>
            @if ($decided && $game['podium'])
                <ol class="mt-1 space-y-0.5">
                    @foreach ($game['podium'] as $place)
                        <li class="flex items-baseline justify-between gap-3 text-sm sm:text-base {{ $place['position'] === 1 ? 'font-bold' : '' }}">
                            <span class="truncate"><span class="figures inline-block w-7 text-[color:var(--muted)]">{{ $place['position'] }}.º</span>{{ $place['name'] }}</span>
                            @if ($place['mark'] !== null)
                                <span class="narrow figures shrink-0 text-lg">{{ $place['mark'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @elseif ($game['kind'] === 'stage')
                {{-- Prueba de podio directo programada (D32): el podio sale en la pestaña Podios. --}}
                <p class="text-sm text-[color:var(--muted)]">Por disputar · {{ $game['stage'] }}</p>
            @else
                <p class="text-sm text-[color:var(--muted)]">
                    {{ $game['participantsCount'] }} {{ $game['participantsCount'] === 1 ? 'participante' : 'participantes' }}
                </p>
            @endif
        @endif
    </div>

    {{-- Competencia, ronda y sede --}}
    <div class="col-start-2 min-w-0 text-sm md:col-start-3 md:border-l md:border-[color:var(--rule)] md:pl-4">
        <p x-show="{{ $mineCheck }}" x-cloak class="mb-1 text-xs font-bold text-[color:var(--lake)]">
            <i class="fa-solid fa-star text-[color:var(--signal)]" aria-hidden="true"></i> Tu delegación
        </p>
        <p class="font-semibold">
            @if ($series)
                <span title="{{ $series['mark'] === null ? 'Finales' : $series['name'] }}"
                      class="mr-1 inline-flex h-5 min-w-5 items-center justify-center rounded px-1 align-[1px] text-xs font-extrabold text-white
                             {{ $series['mark'] === null ? 'bg-[color:var(--ink)]' : 'bg-[color:var(--lake)]' }}">
                    @if ($series['mark'] === null)
                        <i class="fa-solid fa-trophy text-[0.65rem] text-[color:var(--gold)]" aria-hidden="true"></i><span class="sr-only">Finales</span>
                    @else
                        <span class="sr-only">Serie </span>{{ $series['mark'] }}
                    @endif
                </span>
            @endif
            {{ $game['season'] }}
        </p>
        @if ($game['round'] !== \App\Enums\RoundType::Regular)
            <p class="text-[color:var(--muted)]">{{ $game['round']->label() }}</p>
        @endif
        <x-public.venue :venue="$venue" />
    </div>
</li>
