@use('App\Enums\MatchStatus')
@use('App\Enums\RoundType')

@php
    $today = now()->startOfDay();
    $views = ['programacion' => 'Programación', 'resultados' => 'Resultados'];
@endphp

<div class="flex flex-1 flex-col">
    {{-- CABECERA: el nombre de la olimpiada y las dos unicas vistas. --}}
    <header class="bg-[color:var(--lake)] text-white">
        <div class="mx-auto max-w-5xl px-4 pt-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="narrow text-3xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl">
                        {{ $edition?->name ?? config('app.name') }}
                    </h1>
                    @if ($edition?->start_date)
                        <p class="mt-2 text-sm text-white/80 sm:text-base">
                            {{ $edition->start_date->locale('es')->isoFormat('D [de] MMMM') }}
                            @if ($edition->end_date && ! $edition->end_date->isSameDay($edition->start_date))
                                al {{ $edition->end_date->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
                            @else
                                de {{ $edition->start_date->year }}
                            @endif
                        </p>
                    @endif
                </div>

                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                   class="shrink-0 rounded-md border border-white/40 px-3 py-1.5 text-sm font-semibold text-white hover:bg-white hover:text-[color:var(--lake)]">
                    {{ auth()->check() ? 'Ir al panel' : 'Ingresar' }}
                </a>
            </div>

            @if ($editions->count() > 1)
                <div class="mt-4">
                    <label for="public-edition" class="sr-only">Olimpiada</label>
                    <select id="public-edition" wire:model.live="gamesEditionId"
                            class="w-full max-w-md rounded-md border-white/40 bg-white/10 py-1.5 text-sm text-white focus:border-white focus:ring-0 [&>option]:text-[color:var(--ink)]">
                        @foreach ($editions as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $edition?->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <nav class="mt-6 flex gap-6 sm:gap-8" aria-label="Vistas">
                @foreach ($views as $key => $label)
                    @php $active = ($key === 'resultados') === $played; @endphp
                    <button type="button" wire:click="setView('{{ $key }}')" wire:key="view-{{ $key }}"
                            @if ($active) aria-current="page" @endif
                            class="narrow -mb-px flex items-baseline gap-2 border-b-4 pb-2 text-xl font-bold sm:text-2xl
                                   {{ $active ? 'border-[color:var(--signal)] text-white' : 'border-transparent text-white/65 hover:text-white' }}">
                        {{ $label }}
                        <span class="figures text-sm font-semibold {{ $active ? 'text-white/85' : 'text-white/55' }}">{{ $counts[$key] }}</span>
                    </button>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="mx-auto w-full max-w-5xl flex-1 px-4 pb-16">
        @if (! $edition)
            <div class="py-20">
                <p class="narrow text-2xl font-bold">Todavía no hay una olimpiada publicada</p>
                <p class="mt-2 max-w-prose text-[color:var(--muted)]">La programación aparecerá aquí cuando el organizador active las competencias.</p>
            </div>
        @else
            {{-- FILTROS --}}
            <form wire:submit.prevent class="grid gap-2 py-4 sm:grid-cols-[1fr_1fr_1.3fr]" role="search">
                <div>
                    <label for="public-sport" class="sr-only">Deporte</label>
                    <select id="public-sport" wire:model.live="sportId"
                            class="w-full rounded-md border-[color:var(--rule)] bg-[color:var(--wash)] py-2 text-sm text-[color:var(--ink)] focus:border-[color:var(--lake)] focus:ring-0">
                        <option value="">Todos los deportes</option>
                        @foreach ($sports as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="public-season" class="sr-only">Competencia</label>
                    <select id="public-season" wire:model.live="seasonId"
                            class="w-full rounded-md border-[color:var(--rule)] bg-[color:var(--wash)] py-2 text-sm text-[color:var(--ink)] focus:border-[color:var(--lake)] focus:ring-0">
                        <option value="">Todas las competencias</option>
                        @foreach ($seasonOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative">
                    <label for="public-search" class="sr-only">Buscar delegación</label>
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[color:var(--muted)]"></i>
                    <input id="public-search" type="search" wire:model.live.debounce.400ms="search" maxlength="60"
                           placeholder="Buscar delegación"
                           class="w-full rounded-md border-[color:var(--rule)] bg-[color:var(--wash)] py-2 pl-9 text-sm text-[color:var(--ink)] placeholder:text-[color:var(--muted)] focus:border-[color:var(--lake)] focus:ring-0">
                </div>
            </form>

            {{-- SALTO POR DIA: solo cuando hay mas de un dia a la vista. --}}
            @if ($days->count() > 1)
                <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-3" aria-label="Ir a un día">
                    @foreach ($days as $day)
                        <a href="#dia-{{ $day['key'] }}" wire:key="jump-{{ $day['key'] }}"
                           class="narrow shrink-0 rounded-md border px-3 py-1 text-sm font-semibold
                                  {{ $day['date']?->equalTo($today) ? 'border-[color:var(--signal)] text-[color:var(--ink)]' : 'border-[color:var(--rule)] text-[color:var(--muted)] hover:border-[color:var(--lake)] hover:text-[color:var(--lake)]' }}">
                            @if ($day['date'])
                                {{ $day['date']->equalTo($today) ? 'Hoy' : $day['date']->locale('es')->isoFormat('ddd D MMM') }}
                            @else
                                Sin fecha
                            @endif
                        </a>
                    @endforeach
                </nav>
            @endif

            <div wire:loading.class="opacity-50" wire:target="setView, gamesEditionId, sportId, seasonId, search, clearFilters" class="transition-opacity">
                @forelse ($days as $day)
                    @php $date = $day['date']; @endphp
                    <section id="dia-{{ $day['key'] }}" class="day" wire:key="day-{{ $view }}-{{ $day['key'] }}">
                        <div class="sticky top-0 z-10 -mx-4 flex items-baseline justify-between gap-3 border-y border-[color:var(--rule)] bg-[color:var(--wash)] px-4 py-2">
                            <h2 class="narrow text-xl font-bold sm:text-2xl">
                                @if ($date)
                                    {{ ucfirst($date->locale('es')->isoFormat('dddd D [de] MMMM')) }}
                                    @if ($date->equalTo($today))
                                        <span class="ml-2 rounded bg-[color:var(--signal)] px-1.5 py-0.5 align-middle text-xs font-bold text-[color:var(--ink)]">Hoy</span>
                                    @elseif ($date->equalTo($today->copy()->addDay()))
                                        <span class="ml-2 align-middle text-sm font-semibold text-[color:var(--muted)]">Mañana</span>
                                    @elseif ($date->equalTo($today->copy()->subDay()))
                                        <span class="ml-2 align-middle text-sm font-semibold text-[color:var(--muted)]">Ayer</span>
                                    @endif
                                @else
                                    Fecha por confirmar
                                @endif
                            </h2>
                            <span class="figures shrink-0 text-sm text-[color:var(--muted)]">
                                {{ $day['rows']->count() }} {{ $day['rows']->count() === 1 ? 'partido' : 'partidos' }}
                            </span>
                        </div>

                        <ul>
                            @foreach ($day['rows'] as $game)
                                @php
                                    $decided = $game['status'] !== MatchStatus::Pending;
                                    $walkover = $game['status'] === MatchStatus::Walkover;
                                    $venue = $game['venue'];
                                @endphp
                                <li wire:key="match-{{ $game['id'] }}"
                                    class="grid grid-cols-[3.75rem_minmax(0,1fr)] gap-x-4 gap-y-2 border-b border-[color:var(--rule)] py-4 md:grid-cols-[4.5rem_minmax(0,1fr)_minmax(0,17rem)]">

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
                                        @if ($game['round'] !== RoundType::Regular)
                                            <p class="text-[color:var(--muted)]">{{ $game['round']->label() }}</p>
                                        @endif
                                        <p class="text-[color:var(--muted)]">
                                            <i class="fa-solid fa-location-dot mr-1 text-xs" aria-hidden="true"></i>
                                            @if ($venue && $venue->latitude !== null && $venue->longitude !== null)
                                                <a href="https://www.google.com/maps?q={{ (float) $venue->latitude }},{{ (float) $venue->longitude }}"
                                                   target="_blank" rel="noopener"
                                                   class="underline decoration-[color:var(--rule)] underline-offset-2 hover:text-[color:var(--lake)] hover:decoration-[color:var(--lake)]">{{ $venue->name }}</a>
                                            @else
                                                {{ $venue?->name ?? 'Sede por confirmar' }}
                                            @endif
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @empty
                    <div class="py-16">
                        @if ($hasFilters)
                            <p class="narrow text-2xl font-bold">Ningún partido coincide con tu búsqueda</p>
                            <p class="mt-2 text-[color:var(--muted)]">Prueba con otro deporte o revisa el nombre de la delegación.</p>
                            <button type="button" wire:click="clearFilters"
                                    class="mt-5 rounded-md bg-[color:var(--lake)] px-4 py-2 text-sm font-semibold text-white hover:bg-[color:var(--ink)]">
                                Quitar filtros
                            </button>
                        @elseif ($played)
                            <p class="narrow text-2xl font-bold">Todavía no se ha jugado ningún partido</p>
                            <p class="mt-2 text-[color:var(--muted)]">Los marcadores aparecen aquí apenas el organizador cierra cada partido.</p>
                            @if ($counts['programacion'] > 0)
                                <button type="button" wire:click="setView('programacion')"
                                        class="mt-5 rounded-md bg-[color:var(--lake)] px-4 py-2 text-sm font-semibold text-white hover:bg-[color:var(--ink)]">
                                    Ver programación
                                </button>
                            @endif
                        @else
                            <p class="narrow text-2xl font-bold">No quedan partidos por jugar</p>
                            <p class="mt-2 text-[color:var(--muted)]">
                                {{ $counts['resultados'] > 0 ? 'Ya se jugó todo lo programado hasta ahora.' : 'El organizador aún no publica la programación.' }}
                            </p>
                            @if ($counts['resultados'] > 0)
                                <button type="button" wire:click="setView('resultados')"
                                        class="mt-5 rounded-md bg-[color:var(--lake)] px-4 py-2 text-sm font-semibold text-white hover:bg-[color:var(--ink)]">
                                    Ver resultados
                                </button>
                            @endif
                        @endif
                    </div>
                @endforelse
            </div>

            @if ($canLoadMore)
                <div class="pt-6">
                    <button type="button" wire:click="more" wire:loading.attr="disabled" wire:target="more"
                            class="rounded-md border border-[color:var(--lake)] px-4 py-2 text-sm font-semibold text-[color:var(--lake)] hover:bg-[color:var(--lake)] hover:text-white disabled:opacity-60">
                        <span wire:loading.remove wire:target="more">Ver más partidos</span>
                        <span wire:loading wire:target="more">Cargando</span>
                    </button>
                    <span class="figures ml-3 text-sm text-[color:var(--muted)]">{{ $shown }} de {{ $total }}</span>
                </div>
            @endif
        @endif
    </main>
</div>
