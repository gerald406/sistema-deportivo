@use('App\Services\PublicResultsService')

@php
    $today = now()->startOfDay();
    $hrefFor = fn (string $key) => route('home', array_filter([
        'olimpiada' => $editions->count() > 1 ? $edition?->id : null,
        'serie' => $key,
    ]));
@endphp

<div class="flex flex-1 flex-col">
    {{-- CABECERA: la olimpiada y, dentro de una serie, el salto entre series. --}}
    <header class="bg-[color:var(--lake)] text-white">
        <div class="mx-auto max-w-5xl px-4 pt-5 {{ $card && $hasSeries ? '' : 'pb-8 sm:pb-10' }}">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 @class([
                        'narrow font-extrabold leading-[1.05] tracking-tight',
                        'text-3xl sm:text-5xl' => ! $card || ! $hasSeries,
                        'text-2xl sm:text-3xl' => $card && $hasSeries,
                    ])>
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

            @if ($card && $hasSeries)
                <nav class="-mx-4 mt-5 flex items-end gap-5 overflow-x-auto px-4 sm:gap-7" aria-label="Series">
                    <a href="{{ $hrefFor('') }}" wire:click.prevent="closeSeries"
                       class="-mb-px flex shrink-0 items-center gap-2 border-b-4 border-transparent pb-2 text-sm font-semibold text-white/75 hover:text-white">
                        <i class="fa-solid fa-table-cells-large text-xs" aria-hidden="true"></i>
                        Todas las series
                    </a>
                    @foreach ($cards as $option)
                        @php $active = $option['key'] === $card['key']; @endphp
                        <a href="{{ $hrefFor($option['key']) }}" wire:key="series-tab-{{ $option['key'] }}"
                           wire:click.prevent="openSeries('{{ $option['key'] }}')"
                           @if ($active) aria-current="page" @endif
                           class="narrow -mb-px shrink-0 border-b-4 pb-2 text-lg font-bold sm:text-xl
                                  {{ $active ? 'border-[color:var(--signal)] text-white' : 'border-transparent text-white/65 hover:text-white' }}">
                            {{ $option['mark'] === null ? 'Finales' : $option['name'] }}
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>
    </header>

    <main class="mx-auto w-full max-w-5xl flex-1 px-4 pb-16">
        @if (! $edition)
            <div class="py-20">
                <p class="narrow text-2xl font-bold">Todavía no hay una olimpiada publicada</p>
                <p class="mt-2 max-w-prose text-[color:var(--muted)]">La programación aparecerá aquí cuando el organizador active las competencias.</p>
            </div>

        @elseif (! $card)
            {{-- PORTADA: una tarjeta por serie y la de finales. --}}
            <div class="pb-5 pt-8">
                <h2 class="narrow text-3xl font-extrabold sm:text-4xl">Series</h2>
                <p class="mt-1 max-w-prose text-[color:var(--muted)]">Elige una serie para ver su programación, sus resultados y sus podios.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2" wire:loading.class="opacity-60" wire:target="openSeries, gamesEditionId">
                @foreach ($cards as $option)
                    <x-public.series-card :card="$option" :href="$hrefFor($option['key'])"
                                          :wide="$loop->last && $cards->count() % 2 === 1"
                                          wire:key="series-card-{{ $option['key'] }}" />
                @endforeach
            </div>

        @else
            {{-- DETALLE DE UNA SERIE --}}
            @if ($hasSeries)
                <div class="flex items-center gap-4 pt-7">
                    <span @class([
                        'flex h-16 w-16 shrink-0 items-center justify-center rounded-lg text-white',
                        'bg-[color:var(--lake)]' => $card['mark'] !== null,
                        'bg-[color:var(--ink)]' => $card['mark'] === null,
                    ]) aria-hidden="true">
                        @if ($card['mark'] !== null)
                            <span class="narrow text-5xl font-extrabold leading-none">{{ $card['mark'] }}</span>
                        @else
                            <i class="fa-solid fa-trophy text-2xl text-[color:var(--signal)]"></i>
                        @endif
                    </span>
                    <div class="min-w-0">
                        <h2 class="narrow text-3xl font-extrabold leading-tight sm:text-4xl">{{ $card['name'] }}</h2>
                        <p class="text-sm text-[color:var(--muted)]">
                            @if ($card['teams']->isNotEmpty())
                                {{ \Illuminate\Support\Arr::join($card['teams']->all(), ', ', ' y ') }}
                            @else
                                Fase final de cada deporte, finales de podio y pruebas sin serie
                            @endif
                        </p>
                    </div>
                </div>
            @endif

            <div id="listado" class="scroll-mt-2 pt-6">
                {{-- PESTAÑAS --}}
                <div class="flex w-full rounded-lg border border-[color:var(--rule)] bg-[color:var(--wash)] p-1 sm:inline-flex sm:w-auto" role="tablist" aria-label="Qué ver">
                    @foreach ($tabs as $key => $label)
                        @php $active = $key === $activeView; @endphp
                        <button type="button" role="tab" wire:click="setView('{{ $key }}')" wire:key="view-{{ $key }}"
                                aria-selected="{{ $active ? 'true' : 'false' }}"
                                class="narrow flex flex-1 items-baseline justify-center gap-2 rounded-md px-3 py-1.5 text-base font-bold sm:flex-none sm:px-4 sm:text-lg
                                       {{ $active ? 'bg-white text-[color:var(--ink)] shadow-sm' : 'text-[color:var(--muted)] hover:text-[color:var(--ink)]' }}">
                            {{ $label }}
                            <span class="figures text-sm font-semibold {{ $active ? 'text-[color:var(--lake)]' : '' }}">{{ $counts[$key] }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- FILTROS: deporte en fichas; la competencia solo dentro de un deporte. --}}
                @if ($sports->count() > 1)
                    <div class="-mx-4 mt-4 flex gap-2 overflow-x-auto px-4 pb-1" role="group" aria-label="Deporte">
                        <button type="button" wire:click="selectSport('')"
                                @if (! $sport) aria-pressed="true" @else aria-pressed="false" @endif
                                class="shrink-0 rounded-full border px-3 py-1 text-sm font-semibold
                                       {{ ! $sport ? 'border-[color:var(--ink)] bg-[color:var(--ink)] text-white' : 'border-[color:var(--rule)] hover:border-[color:var(--lake)] hover:text-[color:var(--lake)]' }}">
                            Todos los deportes
                        </button>
                        @foreach ($sports as $option)
                            @php $chosen = $sport?->id === $option->id; @endphp
                            <button type="button" wire:click="selectSport('{{ $option->id }}')" wire:key="sport-{{ $option->id }}"
                                    aria-pressed="{{ $chosen ? 'true' : 'false' }}"
                                    class="shrink-0 rounded-full border px-3 py-1 text-sm font-semibold
                                           {{ $chosen ? 'border-[color:var(--ink)] bg-[color:var(--ink)] text-white' : 'border-[color:var(--rule)] hover:border-[color:var(--lake)] hover:text-[color:var(--lake)]' }}">
                                {{ $option->name }}
                            </button>
                        @endforeach
                    </div>
                @endif

                <form wire:submit.prevent role="search"
                      class="mt-3 grid gap-2 {{ $seasonOptions->count() > 1 ? 'sm:grid-cols-2' : '' }}">
                    @if ($seasonOptions->count() > 1)
                        <div>
                            <label for="public-season" class="sr-only">Competencia</label>
                            <select id="public-season" wire:model.live="seasonId"
                                    class="w-full rounded-md border-[color:var(--rule)] bg-[color:var(--wash)] py-2 text-sm text-[color:var(--ink)] focus:border-[color:var(--lake)] focus:ring-0">
                                <option value="">Todas las competencias de {{ $sport->name }}</option>
                                @foreach ($seasonOptions as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="relative">
                        <label for="public-search" class="sr-only">Buscar delegación o participante</label>
                        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[color:var(--muted)]" aria-hidden="true"></i>
                        <input id="public-search" type="search" wire:model.live.debounce.400ms="search" maxlength="60"
                               placeholder="Buscar delegación o participante"
                               class="w-full rounded-md border-[color:var(--rule)] bg-[color:var(--wash)] py-2 pl-9 text-sm text-[color:var(--ink)] placeholder:text-[color:var(--muted)] focus:border-[color:var(--lake)] focus:ring-0">
                    </div>
                </form>
            </div>

            <div class="mt-4 transition-opacity" wire:loading.class="opacity-50"
                 wire:target="setView, openSeries, closeSeries, selectSport, seasonId, search, clearFilters, gotoPage, nextPage, previousPage">
                @if ($activeView === 'podios')
                    {{-- PODIOS, por deporte --}}
                    @forelse ($podiums as $group)
                        <section class="pt-4" wire:key="podium-sport-{{ $group['sport'] }}">
                            <h3 class="narrow border-b border-[color:var(--rule)] pb-2 text-xl font-bold sm:text-2xl">{{ $group['sport'] }}</h3>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($group['stages'] as $stage)
                                    <x-public.podium-card :stage="$stage" wire:key="podium-{{ $stage->id }}" />
                                @endforeach
                            </div>
                        </section>
                    @empty
                        <div class="py-14">
                            @if ($hasFilters)
                                <p class="narrow text-2xl font-bold">Ningún podio coincide con tu búsqueda</p>
                                <p class="mt-2 text-[color:var(--muted)]">Prueba con otro deporte o revisa el nombre.</p>
                                <button type="button" wire:click="clearFilters"
                                        class="mt-5 rounded-md bg-[color:var(--lake)] px-4 py-2 text-sm font-semibold text-white hover:bg-[color:var(--ink)]">
                                    Quitar filtros
                                </button>
                            @else
                                <p class="narrow text-2xl font-bold">Todavía no hay podios</p>
                                <p class="mt-2 max-w-prose text-[color:var(--muted)]">Aparecen aquí apenas el organizador programa una prueba o registra su 1.º, 2.º y 3.º puesto.</p>
                            @endif
                        </div>
                    @endforelse

                    <x-public.pager :paginator="$page" one="podio" many="podios" :page-name="\App\Livewire\Public\PublicResults::PAGE_NAME" />
                @else
                    {{-- PARTIDOS, por dia --}}
                    @forelse ($days as $day)
                        @php $date = $day['date']; @endphp
                        <section class="day" wire:key="day-{{ $activeView }}-{{ $day['key'] }}">
                            <div class="sticky top-0 z-10 -mx-4 flex items-baseline justify-between gap-3 border-y border-[color:var(--rule)] bg-[color:var(--wash)] px-4 py-2">
                                <h3 class="narrow text-xl font-bold sm:text-2xl">
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
                                </h3>
                                @php
                                    $games = $day['rows']->where('kind', 'match')->count();
                                    $events = $day['rows']->count() - $games;
                                @endphp
                                <span class="figures shrink-0 text-sm text-[color:var(--muted)]">
                                    {{ collect([
                                        $games ? $games.' '.($games === 1 ? 'partido' : 'partidos') : null,
                                        $events ? $events.' '.($events === 1 ? 'prueba' : 'pruebas') : null,
                                    ])->filter()->join(' · ') }}
                                </span>
                            </div>

                            <ul>
                                @foreach ($day['rows'] as $game)
                                    <x-public.match-row :game="$game" wire:key="match-{{ $game['id'] }}" />
                                @endforeach
                            </ul>
                        </section>
                    @empty
                        <div class="py-14">
                            @if ($hasFilters)
                                <p class="narrow text-2xl font-bold">Ningún partido coincide con tu búsqueda</p>
                                <p class="mt-2 text-[color:var(--muted)]">Prueba con otro deporte o revisa el nombre de la delegación.</p>
                                <button type="button" wire:click="clearFilters"
                                        class="mt-5 rounded-md bg-[color:var(--lake)] px-4 py-2 text-sm font-semibold text-white hover:bg-[color:var(--ink)]">
                                    Quitar filtros
                                </button>
                            @elseif ($activeView === 'resultados')
                                <p class="narrow text-2xl font-bold">Todavía no se ha jugado ningún partido</p>
                                <p class="mt-2 text-[color:var(--muted)]">Los marcadores aparecen aquí apenas el organizador cierra cada partido.</p>
                                @if ($counts['programacion'] > 0)
                                    <button type="button" wire:click="setView('programacion')"
                                            class="mt-5 rounded-md bg-[color:var(--lake)] px-4 py-2 text-sm font-semibold text-white hover:bg-[color:var(--ink)]">
                                        Ver programación
                                    </button>
                                @endif
                            @else
                                <p class="narrow text-2xl font-bold">No queda nada por jugar</p>
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

                    <x-public.pager :paginator="$page" one="partido" many="partidos" :page-name="\App\Livewire\Public\PublicResults::PAGE_NAME" />
                @endif
            </div>
        @endif
    </main>
</div>
