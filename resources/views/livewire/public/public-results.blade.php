<div class="relative w-full">

    {{-- HEADER PÚBLICO (sin switch futbol/voley: se elige Torneo -> Temporada) --}}
    <div class="relative mb-10 text-center">
        <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-brand-primary/10 px-5 py-2 text-sm font-bold uppercase tracking-widest text-brand-primary">
            <i class="fa-solid fa-bolt"></i> En vivo
        </div>

        <h1 class="font-display text-4xl font-black tracking-tight text-slate-900 md:text-5xl">
            Resultados y <span class="text-brand-primary">Programación</span>
        </h1>

        <div wire:loading wire:target="tournamentId, seasonId, setTab, selectedResultMatchdayId" class="absolute left-1/2 mt-4 -translate-x-1/2">
            <i class="fa-solid fa-circle-notch fa-spin text-2xl text-brand-primary"></i>
        </div>
    </div>

    {{-- SELECTOR DE TORNEO Y TEMPORADA --}}
    <div class="mx-auto mb-10 grid w-full max-w-3xl grid-cols-1 gap-4 px-4 sm:grid-cols-2 sm:px-0">
        <div class="group relative">
            <label for="public-tournament" class="sr-only">Torneo</label>
            <select id="public-tournament" wire:model.live="tournamentId"
                    class="block w-full cursor-pointer appearance-none rounded-2xl border-2 border-slate-200 bg-white px-5 py-4 text-center text-lg font-bold text-slate-800 shadow-sm transition-all focus:border-brand-primary focus:ring-brand-primary group-hover:border-slate-300">
                <option value="">-- Elige un torneo --</option>
                @foreach ($tournaments as $tournament)
                    <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                @endforeach
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-5 text-slate-400 transition-colors group-hover:text-slate-600">
                <i class="fa-solid fa-chevron-down text-lg"></i>
            </div>
        </div>

        <div class="group relative">
            <label for="public-season" class="sr-only">Temporada</label>
            <select id="public-season" wire:model.live="seasonId" @disabled($seasons->isEmpty())
                    class="block w-full cursor-pointer appearance-none rounded-2xl border-2 border-slate-200 bg-white px-5 py-4 text-center text-lg font-bold text-slate-800 shadow-sm transition-all focus:border-brand-primary focus:ring-brand-primary disabled:cursor-not-allowed disabled:bg-slate-100 group-hover:border-slate-300">
                @forelse ($seasons as $option)
                    <option value="{{ $option->id }}">{{ $option->name }} · {{ $option->sport->name }}{{ $option->status === \App\Enums\SeasonStatus::Closed ? ' (cerrada)' : '' }}</option>
                @empty
                    <option value="">Sin temporadas públicas</option>
                @endforelse
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-5 text-slate-400 transition-colors group-hover:text-slate-600">
                <i class="fa-solid fa-chevron-down text-lg"></i>
            </div>
        </div>
    </div>

    @if ($season)
        {{-- NAVEGADOR DE TABS: solo las que esta temporada soporta (SeasonOverviewService::sections) --}}
        @php
            $tabStyles = [
                'standings' => ['Posiciones', 'fa-solid fa-ranking-star', 'bg-brand-primary text-white shadow-lg shadow-brand-primary/30 scale-105'],
                'results' => ['Resultados', 'fa-solid fa-flag-checkered', 'bg-slate-800 text-white shadow-lg shadow-slate-800/30 scale-105'],
                'schedule' => ['Próxima fecha', 'fa-solid fa-calendar-days', 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30 scale-105'],
                'scorers' => ['Goleadores', 'fa-solid fa-futbol', 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 scale-105'],
                'suspended' => ['Suspendidos', 'fa-solid fa-ban', 'bg-red-500 text-white shadow-lg shadow-red-500/30 scale-105'],
                'yellows' => ['Amarillas', 'fa-regular fa-square', 'bg-yellow-500 text-white shadow-lg shadow-yellow-500/30 scale-105'],
            ];
        @endphp
        <div class="mb-10 flex flex-wrap justify-center gap-3">
            @foreach ($tabs as $tab)
                @php [$tabLabel, $tabIcon, $tabActive] = $tabStyles[$tab]; @endphp
                <button type="button" wire:click="setTab('{{ $tab }}')" wire:key="tab-{{ $tab }}"
                        class="rounded-full px-5 py-2.5 text-sm font-bold transition-all duration-300 {{ $activeTab === $tab ? $tabActive : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                    <i class="{{ $tabIcon }} mr-1"></i> {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        {{-- (1) TAB: POSICIONES --}}
        @if ($activeTab === 'standings')
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
                <div class="bg-gradient-to-r from-brand-primary to-brand-secondary px-6 py-4">
                    <h3 class="flex items-center gap-3 font-display text-xl font-bold text-white">
                        <i class="fa-solid fa-ranking-star text-white/80"></i> Tabla de Posiciones
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500">
                                <th class="w-12 px-3 py-3 text-center">Pos</th>
                                <th class="px-3 py-3 text-left">Equipo</th>
                                {{-- Columnas desde los datos de SeasonStanding, no un hardcode futbol/voley --}}
                                @foreach ($standingColumns as $col)
                                    <th class="px-2 py-3 text-center {{ ['PG' => 'text-green-600', 'PE' => 'text-brand-accent', 'PP' => 'text-red-500', 'WO' => 'text-slate-400', 'SG' => 'text-blue-500', 'SP' => 'text-rose-500'][$col['label']] ?? '' }}"
                                        title="{{ $col['title'] }}">{{ $col['label'] }}</th>
                                @endforeach
                                <th class="px-3 py-3 text-center font-black text-brand-primary">PTS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($standings as $row)
                                @php $logo = $row->seasonTeam->team->logo_path; @endphp
                                <tr wire:key="standing-{{ $row->id }}" class="transition-colors duration-200 hover:bg-slate-50 {{ $loop->index < 3 ? 'bg-brand-primary/5' : '' }}">
                                    <td class="px-3 py-3 text-center">
                                        @if ($loop->index === 0)
                                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-400 text-sm font-black text-white shadow-sm">1</span>
                                        @elseif ($loop->index === 1)
                                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-300 text-sm font-black text-slate-700 shadow-sm">2</span>
                                        @elseif ($loop->index === 2)
                                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-700 text-sm font-black text-white shadow-sm">3</span>
                                        @else
                                            <span class="font-bold text-slate-500">{{ $loop->iteration }}</span>
                                        @endif
                                    </td>
                                    <td class="flex items-center gap-3 px-3 py-3 text-left text-sm font-bold text-slate-800 md:text-base">
                                        @if ($logo)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="" class="h-8 w-8 rounded-full border border-slate-200 object-cover">
                                        @else
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-slate-400">
                                                <i class="fa-solid fa-shield-halved text-xs"></i>
                                            </div>
                                        @endif
                                        {{ $row->seasonTeam->team->name }}
                                    </td>
                                    @foreach ($standingColumns as $col)
                                        @php $value = (int) data_get($row, $col['key'], 0); @endphp
                                        @if ($col['key'] === 'extra_stats.diff')
                                            <td class="px-2 py-3 text-center text-sm font-bold {{ $value > 0 ? 'text-green-600' : ($value < 0 ? 'text-red-500' : 'text-slate-500') }}">
                                                {{ $value > 0 ? '+'.$value : $value }}
                                            </td>
                                        @else
                                            <td class="px-2 py-3 text-center text-sm {{ ['PG' => 'font-bold text-green-600', 'PE' => 'text-brand-accent', 'PP' => 'text-red-500', 'WO' => 'text-slate-400', 'SG' => 'font-medium text-blue-600', 'SP' => 'font-medium text-rose-500'][$col['label']] ?? 'text-slate-600' }}">{{ $value }}</td>
                                        @endif
                                    @endforeach
                                    <td class="px-3 py-3 text-center">
                                        <span class="inline-flex min-w-[48px] items-center justify-center rounded-xl bg-brand-primary/10 px-4 py-1.5 text-lg font-black text-brand-primary">{{ $row->points }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($standingColumns) + 3 }}" class="py-12 text-center text-slate-500">
                                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
                                            <i class="fa-solid fa-inbox text-2xl text-slate-400"></i>
                                        </div>
                                        <p class="font-medium">No hay datos de posiciones aún.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- (2) TAB: RESULTADOS --}}
        @if ($activeTab === 'results')
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
                <div class="flex flex-col items-start justify-between gap-3 border-b border-slate-200 bg-slate-50 px-6 py-5 md:flex-row md:items-center">
                    <h3 class="flex items-center gap-3 font-display text-xl font-bold text-slate-800">
                        <i class="fa-solid fa-flag-checkered text-slate-700"></i> Resultados &mdash; <span class="text-brand-primary">{{ $lastMatchday->name ?? 'Sin datos' }}</span>
                    </h3>

                    @if ($completedMatchdays->count() > 1)
                        <div class="w-full md:w-64">
                            <label for="public-matchday" class="sr-only">Jornada</label>
                            <select id="public-matchday" wire:model.live="selectedResultMatchdayId"
                                    class="block w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition-all focus:border-brand-primary focus:ring-brand-primary">
                                <option value="">Última jornada</option>
                                @foreach ($completedMatchdays as $md)
                                    <option value="{{ $md->id }}">{{ $md->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                @if ($lastResults->count() > 0)
                    <div class="divide-y divide-slate-100">
                        @foreach ($lastResults as $game)
                            <div wire:key="result-{{ $game['id'] }}" class="flex items-center justify-between px-6 py-5 transition-colors hover:bg-slate-50">
                                @if ($game['isHeadToHead'])
                                    <div class="flex-1 pr-4 text-right">
                                        <span class="text-base font-bold text-slate-800 md:text-lg">{{ $game['home'] }}</span>
                                    </div>

                                    <div class="flex-shrink-0 px-4 text-center">
                                        @if ($game['status'] === \App\Enums\MatchStatus::Walkover)
                                            <span class="rounded-lg border border-red-200 bg-red-50 px-4 py-1.5 text-xs font-black uppercase tracking-widest text-red-600">W.O.</span>
                                        @else
                                            @php
                                                $h = (float) ($game['homeScore'] ?? 0);
                                                $a = (float) ($game['awayScore'] ?? 0);
                                                $win = 'bg-brand-primary text-white border-brand-primary';
                                                $lose = 'bg-slate-100 border border-slate-200 text-slate-800';
                                            @endphp
                                            <div class="flex items-center gap-3">
                                                <span class="flex h-12 w-12 items-center justify-center rounded-xl text-2xl font-black shadow-inner transition-all {{ $h > $a ? $win : $lose }}">{{ $game['homeScore'] ?? 0 }}</span>
                                                <span class="text-xl font-bold text-slate-400">-</span>
                                                <span class="flex h-12 w-12 items-center justify-center rounded-xl text-2xl font-black shadow-inner transition-all {{ $a > $h ? $win : $lose }}">{{ $game['awayScore'] ?? 0 }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex-1 pl-4">
                                        <span class="text-base font-bold text-slate-800 md:text-lg">{{ $game['away'] }}</span>
                                    </div>
                                @else
                                    {{-- Prueba individual o posta: no hay local/visitante, se muestra el ganador --}}
                                    <div class="flex-1 pr-4 text-right">
                                        <span class="text-base font-bold text-slate-800 md:text-lg">{{ $game['event'] }}</span>
                                        <p class="text-xs text-slate-500">{{ $game['participantsCount'] }} participantes</p>
                                    </div>
                                    <div class="flex-shrink-0 px-4 text-center">
                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-amber-400 text-lg font-black text-white shadow-sm">1.º</span>
                                    </div>
                                    <div class="flex-1 pl-4">
                                        <span class="text-base font-bold text-slate-800 md:text-lg">{{ $game['winner'] ?? 'Sin puestos cargados' }}</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-16 text-center text-slate-500">
                        <i class="fa-solid fa-calendar-xmark mb-3 text-4xl text-slate-300"></i>
                        <p class="font-medium">No hay resultados registrados aún.</p>
                    </div>
                @endif
            </div>
        @endif

        {{-- (3) TAB: PRÓXIMA FECHA --}}
        @if ($activeTab === 'schedule')
            <div class="transition-opacity duration-300">
                @if ($nextMatchday && $nextMatchday->observations)
                    <div class="mb-6 rounded-r-lg border-l-4 border-blue-500 bg-blue-50 p-4 shadow-sm">
                        <h3 class="mb-1 text-sm font-bold uppercase tracking-wider text-blue-800"><i class="fa-solid fa-bullhorn mr-1"></i> Comunicado</h3>
                        <p class="whitespace-pre-line text-sm leading-relaxed text-blue-700">{{ $nextMatchday->observations }}</p>
                    </div>
                @endif

                <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
                        <h3 class="flex items-center gap-3 font-display text-xl font-bold text-slate-800">
                            <i class="fa-regular fa-calendar-days text-emerald-500"></i> Próxima Fecha &mdash; <span class="text-emerald-600">{{ $nextMatchday->name ?? 'Por definir' }}</span>
                        </h3>
                    </div>

                    @if ($nextGames->count() > 0)
                        <div class="divide-y divide-slate-100">
                            @foreach ($nextGames as $game)
                                @php $venue = $game['venue']; @endphp
                                <div wire:key="next-{{ $game['id'] }}" class="flex flex-col items-center justify-between gap-4 px-6 py-6 transition-colors hover:bg-slate-50 md:flex-row">
                                    <div class="w-full flex-1 text-center md:pr-6 md:text-right">
                                        <span class="text-lg font-black text-slate-800 md:text-xl">{{ $game['isHeadToHead'] ? $game['home'] : $game['event'] }}</span>
                                    </div>

                                    <div class="flex flex-shrink-0 flex-col items-center px-4 text-center">
                                        <span class="mb-3 rounded-full bg-slate-800 px-5 py-1.5 text-sm font-black tracking-widest text-white shadow-md">{{ $game['isHeadToHead'] ? 'VS' : 'PRUEBA' }}</span>
                                        <div class="mb-1 text-sm font-bold text-slate-600">
                                            @if ($game['scheduledAt'])
                                                <i class="fa-regular fa-clock text-emerald-500"></i> {{ $game['scheduledAt']->format('d/m H:i') }}
                                            @else
                                                <span class="text-amber-500">Por definir</span>
                                            @endif
                                        </div>
                                        <div class="mb-2 text-xs font-medium text-slate-500"><i class="fa-solid fa-location-dot"></i> {{ $venue?->name ?? 'Sede por definir' }}</div>

                                        @if ($venue && $venue->latitude !== null && $venue->longitude !== null)
                                            {{-- @js() escapa nombre/direccion (la referencia armaba el JS con addslashes: XSS y
                                                 se rompia con un apostrofe). --}}
                                            <button type="button"
                                                    x-on:click="$dispatch('open-venue-modal', {{ \Illuminate\Support\Js::from(['name' => $venue->name, 'address' => $venue->address, 'lat' => (float) $venue->latitude, 'lng' => (float) $venue->longitude]) }})"
                                                    class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 shadow-sm transition hover:bg-blue-200"
                                                    title="Ver ubicación en el mapa">
                                                <i class="fa-regular fa-map fa-sm"></i> Ver Mapa
                                            </button>
                                        @endif
                                    </div>

                                    <div class="w-full flex-1 text-center md:pl-6 md:text-left">
                                        <span class="text-lg font-black text-slate-800 md:text-xl">{{ $game['isHeadToHead'] ? $game['away'] : $game['participantsCount'].' participantes' }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-16 text-center text-slate-500">
                            <i class="fa-regular fa-calendar-xmark mb-3 text-4xl text-slate-300"></i>
                            <p class="font-medium">Programación no disponible por el momento.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- (4) TAB: GOLEADORES (solo si la temporada los soporta) --}}
        @if ($activeTab === 'scorers')
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
                <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
                    <h3 class="font-display text-xl font-bold text-slate-800"><i class="fa-solid fa-futbol mr-2 text-indigo-600"></i> Top 10 Goleadores</h3>
                </div>
                @if ($topScorers->count() > 0)
                    <div class="divide-y divide-slate-100">
                        @foreach ($topScorers as $scorer)
                            <div wire:key="scorer-{{ $scorer['entry']->id }}" class="flex items-center gap-5 px-6 py-4 hover:bg-slate-50">
                                <div class="w-8 flex-shrink-0 text-center text-xl">
                                    @if ($loop->index === 0) 🥇 @elseif ($loop->index === 1) 🥈 @elseif ($loop->index === 2) 🥉 @else <span class="font-bold text-slate-400">{{ $loop->iteration }}</span> @endif
                                </div>
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 shadow-inner">
                                    <span class="font-black text-slate-700">{{ $scorer['entry']->shirt_number ?? '-' }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-base font-bold text-slate-800">{{ $scorer['entry']->player->last_name }}, {{ $scorer['entry']->player->first_name }}</p>
                                    <p class="text-sm font-medium text-brand-primary">{{ $scorer['entry']->seasonTeam->team->name }}</p>
                                </div>
                                <span class="rounded-xl bg-indigo-50 px-4 py-1.5 text-xl font-black text-indigo-600">{{ $scorer['total'] }} <i class="fa-solid fa-futbol ml-1 text-[10px] opacity-50"></i></span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-16 text-center text-slate-500"><p>Aún no hay goles registrados.</p></div>
                @endif
            </div>
        @endif

        {{-- (5) TAB: SUSPENDIDOS --}}
        @if ($activeTab === 'suspended')
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
                <div class="border-b border-red-600 bg-red-500 px-6 py-5">
                    <h3 class="font-display text-xl font-bold text-white"><i class="fa-solid fa-ban mr-2 text-red-200"></i> Jugadores Suspendidos</h3>
                </div>
                @if ($suspensions->count() > 0)
                    <div class="divide-y divide-slate-100">
                        @foreach ($suspensions as $sus)
                            <div wire:key="sus-{{ $sus->id }}" class="flex items-center justify-between p-4">
                                <div>
                                    <p class="font-bold text-slate-800">{{ $sus->seasonTeamPlayer->player->first_name }} {{ $sus->seasonTeamPlayer->player->last_name }}</p>
                                    <p class="text-sm text-slate-500">{{ $sus->seasonTeamPlayer->seasonTeam->team->name }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="rounded-lg bg-red-100 px-3 py-1 text-sm font-black text-red-700">{{ $sus->matches_suspended }} {{ $sus->matches_suspended === 1 ? 'Fecha' : 'Fechas' }}</span>
                                    <p class="mt-1 text-xs font-bold text-red-400">{{ $sus->reason }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-16 text-center text-slate-500"><p>No hay jugadores suspendidos.</p></div>
                @endif
            </div>
        @endif

        {{-- (6) TAB: AMARILLAS --}}
        @if ($activeTab === 'yellows')
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
                <div class="border-b border-yellow-600 bg-yellow-500 px-6 py-5">
                    <h3 class="font-display text-xl font-bold text-white"><i class="fa-regular fa-square mr-2 text-yellow-100"></i> Alerta de Amarillas</h3>
                    {{-- La referencia decia "la sancion se activa al acumular 3": en este sistema las
                         amarillas no se acumulan (la doble amarilla en un partido es una roja). --}}
                    <p class="mt-1 text-xs text-yellow-50">Jugadores con 2 o más amarillas en la temporada. Las amarillas no se acumulan: la doble amarilla en un mismo partido es roja.</p>
                </div>
                @if ($yellowCardRisks->count() > 0)
                    <div class="divide-y divide-slate-100">
                        @foreach ($yellowCardRisks as $risk)
                            <div wire:key="yellow-{{ $risk['entry']->id }}" class="flex items-center justify-between p-4">
                                <div>
                                    <p class="font-bold text-slate-800">{{ $risk['entry']->player->first_name }} {{ $risk['entry']->player->last_name }}</p>
                                    <p class="text-sm text-slate-500">{{ $risk['entry']->seasonTeam->team->name }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex gap-1" aria-label="{{ $risk['total'] }} amarillas">
                                        @for ($i = 0; $i < min($risk['total'], 10); $i++)
                                            <div class="h-6 w-4 rounded-sm border border-yellow-500 bg-yellow-400 shadow-sm"></div>
                                        @endfor
                                    </div>
                                    @if ($risk['suspended'])
                                        <span class="ml-2 rounded-full bg-red-100 px-2 py-1 text-[10px] font-black uppercase text-red-700">Suspendido</span>
                                    @else
                                        <span class="ml-2 rounded-full bg-amber-100 px-2 py-1 text-[10px] font-black uppercase text-amber-700">En observación</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-16 text-center text-slate-500"><p>No hay jugadores con 2 o más amarillas.</p></div>
                @endif
            </div>
        @endif
    @elseif ($tournaments->isEmpty())
        <div class="mx-auto max-w-xl rounded-3xl border border-slate-200 bg-white py-16 text-center text-slate-500 shadow-xl shadow-slate-200/50">
            <i class="fa-solid fa-trophy mb-3 text-4xl text-slate-300"></i>
            <p class="font-medium">Todavía no hay torneos publicados.</p>
        </div>
    @endif

    <div class="mb-8 mt-12 text-center">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-6 py-3 font-semibold text-slate-600 transition-all hover:border-brand-primary hover:text-brand-primary hover:shadow-md">
            <i class="fa-solid fa-arrow-left"></i> Volver a la página principal
        </a>
    </div>

    {{-- MODAL DEL MAPA. wire:ignore: Livewire no debe tocar el DOM que arma Leaflet.
         El mapa vive en el estado de Alpine (sin funciones globales) y el popup se arma
         con textContent (la referencia inyectaba el nombre de la sede como HTML). --}}
    <div wire:ignore
         x-data="{
             open: false,
             venue: { name: '', address: '', lat: null, lng: null },
             map: null,
             show(detail) {
                 this.venue = detail;
                 this.open = true;
                 this.$nextTick(() => setTimeout(() => this.draw(), 150));
             },
             draw() {
                 if (typeof L === 'undefined') return;
                 if (this.map) { this.map.remove(); this.map = null; }
                 this.map = L.map(this.$refs.map).setView([this.venue.lat, this.venue.lng], 15);
                 {{-- Teselas estandar de OpenStreetMap: las de CARTO (basemaps.cartocdn.com) que usaba
                      la referencia ahora exigen API key y devuelven una marca de agua. --}}
                 L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                     attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a> contributors',
                     maxZoom: 19,
                 }).addTo(this.map);
                 {{-- Dentro de un modal Leaflet calcula mal el tamano (mapa gris/descentrado). --}}
                 this.map.invalidateSize();
                 const popup = document.createElement('div');
                 const title = document.createElement('b');
                 title.textContent = this.venue.name;
                 const address = document.createElement('div');
                 address.style.fontSize = '12px';
                 address.textContent = this.venue.address || '';
                 popup.append(title, address);
                 L.marker([this.venue.lat, this.venue.lng]).addTo(this.map).bindPopup(popup).openPopup();
             },
             osmUrl() {
                 return 'https://www.openstreetmap.org/?mlat=' + this.venue.lat + '&mlon=' + this.venue.lng + '#map=16/' + this.venue.lat + '/' + this.venue.lng;
             },
         }"
         x-on:open-venue-modal.window="show($event.detail)"
         x-on:keydown.escape.window="open = false">
        <div x-show="open" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
             x-on:click.self="open = false">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true">
                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
                    <h3 class="font-display text-xl font-bold text-slate-800">
                        <i class="fa-solid fa-map-location-dot mr-2 text-blue-600"></i>
                        <span x-text="venue.name"></span>
                    </h3>
                    <button type="button" x-on:click="open = false" class="text-slate-400 transition-colors hover:text-red-500" aria-label="Cerrar">
                        <i class="fa-solid fa-times text-2xl"></i>
                    </button>
                </div>
                <div class="p-4">
                    <div class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-600">
                        <i class="fa-solid fa-location-dot text-red-500"></i>
                        <span x-text="venue.address || 'Dirección no registrada'"></span>
                    </div>
                    <div x-ref="map" class="h-80 w-full overflow-hidden rounded-xl border border-slate-200 shadow-inner md:h-96"></div>
                    <div class="mt-3 text-right">
                        <a x-bind:href="osmUrl()" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 text-sm font-bold text-blue-600 hover:underline">
                            <i class="fa-regular fa-map"></i> Abrir ruta en GPS
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
