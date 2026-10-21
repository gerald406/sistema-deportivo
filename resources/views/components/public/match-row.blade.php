{{-- Un partido o prueba de la pagina publica: hora, contendientes con marcador y,
     a la derecha, competencia, ronda y sede. $game sale de SeasonOverviewService::matchRow() o stageRow(). --}}
@props(['game'])

@php
    $decided = $game['status'] !== \App\Enums\MatchStatus::Pending;
    $walkover = $game['status'] === \App\Enums\MatchStatus::Walkover;
    $venue = $game['venue'];
@endphp

<li {{ $attributes->class('grid grid-cols-[3.75rem_minmax(0,1fr)] gap-x-4 gap-y-2 border-b border-[color:var(--rule)] py-4 md:grid-cols-[4.5rem_minmax(0,1fr)_minmax(0,17rem)]') }}>

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
        <p class="font-semibold">{{ $game['season'] }}</p>
        @if ($game['round'] !== \App\Enums\RoundType::Regular)
            <p class="text-[color:var(--muted)]">{{ $game['round']->label() }}</p>
        @endif
        <x-public.venue :venue="$venue" />
    </div>
</li>
