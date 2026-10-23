@use('App\Services\PublicResultsService')

@php
    $today = now()->startOfDay();
    $hrefFor = fn (string $key) => route('home', array_filter([
        'olimpiada' => $editions->count() > 1 ? $edition?->id : null,
        'serie' => $key,
    ]));
    // El boton "Filtros" cuenta lo que esta dentro del panel (la fecha y la busqueda van fuera).
    $panelFilters = $edition ? collect($activeFilters)->whereNotIn('property', ['date', 'search'])->count() : 0;
@endphp

{{-- Estado del navegador: hoja de filtros, buscador en movil y "Mi delegacion"
     (se recuerda por olimpiada en localStorage; sin almacenamiento, no se recuerda). --}}
<div class="flex flex-1 flex-col"
     x-data="{
        filters: false,
        searching: @js(($search ?? '') !== ''),
        mine: null,
        names: @js($edition ? $teams->pluck('name', 'id') : []),
        key: @js('mi-delegacion-'.($edition?->id ?? 0)),
        init() {
            try { this.mine = Number(localStorage.getItem(this.key)) || null } catch (e) {}
            if (this.mine && ! this.names[this.mine]) this.mine = null
        },
        setMine(id) {
            this.mine = this.mine === id ? null : id
            try { this.mine ? localStorage.setItem(this.key, this.mine) : localStorage.removeItem(this.key) } catch (e) {}
        },
     }"
     x-on:keydown.escape.window="filters = false">

    {{-- CABECERA: la olimpiada, el buscador y las pestañas (desde sm). --}}
    <header class="bg-[color:var(--lake)] text-white">
        <div class="mx-auto max-w-6xl px-4 pt-4 sm:pt-6 {{ $edition ? 'pb-4 sm:pb-0' : 'pb-8 sm:pb-10' }}">
            <div class="flex items-start justify-between gap-3 sm:items-center sm:gap-6">
                <div class="min-w-0">
                    <h1 class="narrow text-xl font-extrabold leading-[1.05] tracking-tight sm:text-3xl">
                        {{ $edition?->name ?? config('app.name') }}
                    </h1>
                    @if ($edition?->start_date)
                        <p class="mt-1 text-sm text-white/80">
                            {{ $edition->start_date->locale('es')->isoFormat('D [de] MMMM') }}
                            @if ($edition->end_date && ! $edition->end_date->isSameDay($edition->start_date))
                                al {{ $edition->end_date->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
                            @else
                                de {{ $edition->start_date->year }}
                            @endif
                        </p>
                    @endif
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    @if ($edition)
                        <x-public.search input-id="public-search" :suggestions="$suggestions" class="hidden w-72 sm:block lg:w-96" />
                        <button type="button" x-on:click="searching = ! searching; $nextTick(() => searching && document.getElementById('public-search-mobile')?.focus())"
                                aria-label="Buscar" :aria-expanded="searching"
                                class="flex h-11 w-11 items-center justify-center rounded-lg border border-white/40 hover:bg-white hover:text-[color:var(--lake)] sm:hidden">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </button>
                    @endif
                    <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                       class="flex h-11 items-center rounded-lg border border-white/40 px-3 text-sm font-semibold text-white hover:bg-white hover:text-[color:var(--lake)]">
                        {{ auth()->check() ? 'Ir al panel' : 'Ingresar' }}
                    </a>
                </div>
            </div>

            @if ($edition)
                <div x-show="searching" x-cloak class="mt-3 sm:hidden">
                    <x-public.search input-id="public-search-mobile" :suggestions="$suggestions" panel-class="inset-x-0" />
                </div>
            @endif

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

            @if ($edition)
                <x-public.tabs :tabs="$tabs" :active="$activeView" :counts="$counts" :totals="$totals" />
            @endif
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 pb-16">
        @if (! $edition)
            <div class="py-20">
                <p class="narrow text-2xl font-bold">Todavía no hay una olimpiada publicada</p>
                <p class="mt-2 max-w-prose text-[color:var(--muted)]">La programación aparecerá aquí cuando el organizador active las competencias.</p>
            </div>

        @elseif ($activeView === 'series')
            {{-- SERIES: cada tarjeta abre la programacion filtrada por su serie. --}}
            <div class="pb-5 pt-8">
                <h2 class="narrow text-3xl font-extrabold sm:text-4xl">Series</h2>
                <p class="mt-1 max-w-prose text-[color:var(--muted)]">Elige una serie para ver su programación; sus resultados y podios quedan a una pestaña.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2" wire:loading.class="opacity-60" wire:target="openSeries">
                @foreach ($cards as $option)
                    <x-public.series-card :card="$option" :href="$hrefFor($option['key'])"
                                          :wide="$loop->last && $cards->count() % 2 === 1"
                                          wire:key="series-card-{{ $option['key'] }}" />
                @endforeach
            </div>

        @else
            <div class="pt-5 lg:grid lg:grid-cols-[16.5rem_minmax(0,1fr)] lg:items-start lg:gap-10 lg:pt-8">

                {{-- FILTROS: columna fija en escritorio, hoja inferior en movil. --}}
                <div x-show="filters" x-cloak x-transition.opacity x-on:click="filters = false"
                     class="fixed inset-0 z-40 bg-[color:var(--ink)]/50 lg:hidden" aria-hidden="true"></div>

                <aside id="filtros" aria-label="Filtros"
                       class="fixed inset-x-0 bottom-0 z-50 flex max-h-[85vh] flex-col rounded-t-2xl bg-white shadow-2xl transition-transform duration-200 max-lg:invisible max-lg:translate-y-full lg:static lg:z-auto lg:max-h-none lg:rounded-none lg:shadow-none"
                       :class="{ 'max-lg:invisible max-lg:translate-y-full': ! filters }">
                    <div class="flex items-center justify-between border-b border-[color:var(--rule)] py-2 pl-4 pr-2 lg:hidden">
                        <p class="narrow text-2xl font-extrabold">Filtros</p>
                        <button type="button" x-on:click="filters = false" aria-label="Cerrar filtros"
                                class="flex h-11 w-11 items-center justify-center rounded-lg text-[color:var(--muted)] hover:bg-[color:var(--wash)]">
                            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="flex-1 space-y-5 overflow-y-auto px-4 py-4 lg:overflow-visible lg:p-0">
                        @if ($hasSeries)
                            <x-public.filter-group label="Serie">
                                <x-public.chip :active="$card === null" wire:click="$set('series', '')">Todas</x-public.chip>
                                @foreach ($cards as $option)
                                    <x-public.chip :active="$card && $card['key'] === $option['key']" wire:key="series-chip-{{ $option['key'] }}"
                                                   wire:click="$set('series', '{{ $option['key'] }}')">
                                        {{ $option['mark'] ?? 'Finales' }}
                                    </x-public.chip>
                                @endforeach
                            </x-public.filter-group>
                        @endif

                        @if ($activeView === 'podios')
                            <x-public.filter-group label="Estado">
                                <x-public.chip :active="$activeState === null" wire:click="$set('state', '')">Todos</x-public.chip>
                                <x-public.chip :active="$activeState === PublicResultsService::DECIDED" wire:click="$set('state', '{{ PublicResultsService::DECIDED }}')">Definidos</x-public.chip>
                                <x-public.chip :active="$activeState === PublicResultsService::PENDING" wire:click="$set('state', '{{ PublicResultsService::PENDING }}')">Por disputar</x-public.chip>
                            </x-public.filter-group>
                        @endif

                        @if ($sports->count() > 1)
                            <x-public.filter-group label="Deporte">
                                <x-public.chip :active="$sport === null" wire:click="$set('sportId', '')">Todos</x-public.chip>
                                @foreach ($sports as $option)
                                    @php $count = $sportCounts[$option->id] ?? ['total' => 0, 'decided' => 0]; @endphp
                                    <x-public.chip :active="$sport?->id === $option->id" wire:key="sport-{{ $option->id }}"
                                                   wire:click="$set('sportId', '{{ $option->id }}')">
                                        {{ $option->name }}
                                        <span class="figures text-xs font-bold opacity-70">
                                            {{ $activeView === 'podios' ? "{$count['decided']}/{$count['total']}" : $count['total'] }}
                                        </span>
                                    </x-public.chip>
                                @endforeach
                            </x-public.filter-group>
                        @endif

                        @if ($disciplines->count() > 1)
                            <x-public.filter-group :label="'Prueba de '.$sport->name">
                                <x-public.chip :active="$discipline === null" wire:click="$set('disciplineId', '')">Todas</x-public.chip>
                                @foreach ($disciplines as $option)
                                    <x-public.chip :active="$discipline?->id === $option->id" wire:key="discipline-{{ $option->id }}"
                                                   wire:click="$set('disciplineId', '{{ $option->id }}')">
                                        {{ $option->name }}
                                    </x-public.chip>
                                @endforeach
                            </x-public.filter-group>
                        @endif

                        @if ($categories->count() > 1)
                            <x-public.filter-group label="Nivel">
                                <x-public.chip :active="$category === null" wire:click="$set('categoryId', '')">Todos</x-public.chip>
                                @foreach ($categories as $option)
                                    <x-public.chip :active="$category?->id === $option->id" wire:key="category-{{ $option->id }}"
                                                   wire:click="$set('categoryId', '{{ $option->id }}')">
                                        {{ $option->name }}
                                    </x-public.chip>
                                @endforeach
                            </x-public.filter-group>
                        @endif

                        @if ($genders->count() > 1)
                            <x-public.filter-group label="Género">
                                <x-public.chip :active="$activeGender === null" wire:click="$set('gender', '')">Todos</x-public.chip>
                                @foreach ($genders as $option)
                                    <x-public.chip :active="$activeGender === $option" wire:key="gender-{{ $option->value }}"
                                                   wire:click="$set('gender', '{{ $option->value }}')">
                                        {{ $option->label() }}
                                    </x-public.chip>
                                @endforeach
                            </x-public.filter-group>
                        @endif

                        @if ($teams->isNotEmpty())
                            {{-- Delegacion: lista con buscador propio (en el navegador), no un select. --}}
                            {{-- "Mi delegacion" (estrella) solo se recuerda en este navegador y la resalta en las listas. --}}
                            <div role="group" aria-label="Delegación" x-data="{ q: '' }">
                                <p class="mb-2 text-xs font-bold uppercase tracking-wider text-[color:var(--muted)]">Delegación</p>
                                <div x-show="mine" x-cloak class="mb-3 flex items-center gap-2 rounded-lg bg-[color:var(--wash)] px-3 py-2 text-sm">
                                    <i class="fa-solid fa-star text-[color:var(--signal)]" aria-hidden="true"></i>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-xs text-[color:var(--muted)]">Mi delegación</span>
                                        <span class="block truncate font-bold" x-text="names[mine]"></span>
                                    </span>
                                    @if (! $team)
                                        <button type="button" x-on:click="$wire.set('teamId', String(mine))"
                                                class="h-9 shrink-0 rounded-full border border-[color:var(--lake)] px-3 text-xs font-bold text-[color:var(--lake)] hover:bg-white">
                                            Ver solo la mía
                                        </button>
                                    @endif
                                </div>
                                @if ($team)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-public.chip :active="true" wire:click="$set('teamId', '')" aria-label="Quitar delegación {{ $team->name }}">
                                            {{ $team->name }} <i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i>
                                        </x-public.chip>
                                        <button type="button" x-on:click="setMine({{ $team->id }})" :aria-pressed="mine === {{ $team->id }}"
                                                class="inline-flex h-10 items-center gap-1.5 rounded-full border border-[color:var(--rule)] px-3 text-xs font-bold text-[color:var(--lake)] hover:border-[color:var(--lake)] lg:h-9">
                                            <i class="fa-star" :class="mine === {{ $team->id }} ? 'fa-solid text-[color:var(--signal)]' : 'fa-regular'" aria-hidden="true"></i>
                                            <span x-text="mine === {{ $team->id }} ? 'Es mi delegación' : 'Es la mía'">Es la mía</span>
                                        </button>
                                    </div>
                                @else
                                    <label for="team-filter" class="sr-only">Buscar delegación</label>
                                    <input id="team-filter" type="search" x-model="q" placeholder="Buscar delegación"
                                           class="h-10 w-full rounded-lg border-[color:var(--rule)] bg-[color:var(--wash)] text-sm text-[color:var(--ink)] placeholder:text-[color:var(--muted)] focus:border-[color:var(--lake)] focus:ring-0">
                                    <ul class="mt-2 max-h-56 overflow-y-auto rounded-lg border border-[color:var(--rule)]">
                                        @foreach ($teams as $option)
                                            <li wire:key="team-{{ $option->id }}" class="flex items-center"
                                                x-show="@js(\Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($option->name))).includes(q.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''))">
                                                <button type="button" wire:click="$set('teamId', '{{ $option->id }}')"
                                                        class="flex min-h-10 min-w-0 flex-1 items-center px-3 py-2 text-left text-sm hover:bg-[color:var(--wash)] hover:text-[color:var(--lake)]">
                                                    {{ $option->name }}
                                                </button>
                                                <button type="button" x-on:click="setMine({{ $option->id }})" :aria-pressed="mine === {{ $option->id }}"
                                                        aria-label="Marcar {{ $option->name }} como mi delegación"
                                                        class="flex h-10 w-10 shrink-0 items-center justify-center text-[color:var(--muted)] hover:text-[color:var(--signal)]">
                                                    <i class="fa-star" :class="mine === {{ $option->id }} ? 'fa-solid text-[color:var(--signal)]' : 'fa-regular'" aria-hidden="true"></i>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="border-t border-[color:var(--rule)] px-4 py-3 lg:hidden">
                        <button type="button" x-on:click="filters = false"
                                class="h-12 w-full rounded-lg bg-[color:var(--lake)] text-base font-bold text-white hover:bg-[color:var(--ink)]">
                            Ver la lista ({{ $page->total() }})
                        </button>
                    </div>
                </aside>

                {{-- LISTADO --}}
                <div id="listado" class="min-w-0 scroll-mt-2">
                    @if ($activeView !== 'podios')
                        <x-public.day-strip :days="$strip" :active="$activeDate" />
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="button" x-on:click="filters = true" aria-controls="filtros" :aria-expanded="filters"
                                class="inline-flex h-10 items-center gap-2 rounded-full border px-4 text-sm font-bold lg:hidden
                                       {{ $panelFilters > 0 ? 'border-[color:var(--ink)] bg-[color:var(--ink)] text-white' : 'border-[color:var(--ink)] bg-white' }}">
                            <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                            Filtros{{ $panelFilters > 0 ? ' · '.$panelFilters : '' }}
                        </button>
                        @foreach ($activeFilters as $filter)
                            <button type="button" wire:key="active-{{ $filter['property'] }}" wire:click="$set('{{ $filter['property'] }}', '')"
                                    aria-label="Quitar filtro {{ $filter['label'] }}"
                                    class="inline-flex h-10 items-center gap-2 rounded-full border border-[color:var(--rule)] bg-[color:var(--wash)] pl-3 pr-2.5 text-sm font-semibold hover:border-[color:var(--lake)] lg:h-9">
                                {{ $filter['label'] }}
                                <i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i>
                            </button>
                        @endforeach
                        @if ($hasFilters)
                            <button type="button" wire:click="clearFilters" class="h-10 px-1 text-sm font-bold text-[color:var(--lake)] hover:underline lg:h-9">
                                Limpiar todo
                            </button>
                        @endif
                    </div>

                    <div class="mt-4 transition-opacity" wire:loading.class="opacity-50">
                        @if ($activeView === 'podios')
                            {{-- PODIOS, por deporte, con el avance de lo que se ve --}}
                            <x-public.podium-progress :label="$progress['label']" :decided="$progress['decided']" :total="$progress['total']" class="mb-2" />

                            @forelse ($podiums as $group)
                                @php $sportCount = $discipline === null ? ($sportCounts[$group['sportId']] ?? null) : null; @endphp
                                <section class="pt-4" wire:key="podium-sport-{{ $group['sportId'] }}">
                                    <div class="flex items-baseline justify-between gap-3 border-b border-[color:var(--rule)] pb-2">
                                        <h3 class="narrow text-xl font-bold sm:text-2xl">{{ $group['sport'] }}</h3>
                                        @if ($sportCount)
                                            <span class="figures shrink-0 text-sm text-[color:var(--muted)]">{{ $sportCount['decided'] }} de {{ $sportCount['total'] }} definidos</span>
                                        @endif
                                    </div>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        @foreach ($group['stages'] as $stage)
                                            <x-public.podium-card :stage="$stage" :compact="$discipline !== null" wire:key="podium-{{ $stage->id }}" />
                                        @endforeach
                                    </div>
                                </section>
                            @empty
                                <div class="py-14">
                                    @if ($hasFilters)
                                        <p class="narrow text-2xl font-bold">Ningún podio coincide con los filtros</p>
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
                            {{-- PARTIDOS, por dia: de la fecha mas reciente a la mas antigua --}}
                            @forelse ($days as $day)
                                @php $dayDate = $day['date']; @endphp
                                <section class="day" wire:key="day-{{ $activeView }}-{{ $day['key'] }}">
                                    <div class="sticky top-0 z-10 -mx-4 flex items-baseline justify-between gap-3 border-y border-[color:var(--rule)] bg-[color:var(--wash)] px-4 py-2 lg:mx-0">
                                        <h3 class="narrow text-xl font-bold sm:text-2xl">
                                            @if ($dayDate)
                                                {{ ucfirst($dayDate->locale('es')->isoFormat('dddd D [de] MMMM')) }}
                                                @if ($dayDate->equalTo($today))
                                                    <span class="ml-2 rounded bg-[color:var(--signal)] px-1.5 py-0.5 align-middle text-xs font-bold text-[color:var(--ink)]">Hoy</span>
                                                @elseif ($dayDate->equalTo($today->copy()->addDay()))
                                                    <span class="ml-2 align-middle text-sm font-semibold text-[color:var(--muted)]">Mañana</span>
                                                @elseif ($dayDate->equalTo($today->copy()->subDay()))
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
                                            <x-public.match-row :game="$game" wire:key="match-{{ $game['id'] }}"
                                                                :series="$seriesBadges[$game['seriesId'] === null ? PublicResultsService::FINALS : (string) $game['seriesId']] ?? null" />
                                        @endforeach
                                    </ul>
                                </section>
                            @empty
                                <div class="py-14">
                                    @if ($hasFilters)
                                        <p class="narrow text-2xl font-bold">Ningún partido coincide con los filtros</p>
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
                </div>
            </div>
        @endif
    </main>

    @if ($edition)
        <x-public.tabs variant="bottom" :tabs="$tabs" :active="$activeView" :counts="$counts" :totals="$totals" />
    @endif
</div>
