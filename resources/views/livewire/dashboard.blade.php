<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Panel de control</h1>
        <p class="text-sm text-gray-500">
            Bienvenido, {{ auth()->user()->name }}
            <span class="text-gray-400">({{ auth()->user()->getRoleNames()->implode(', ') }})</span>
        </p>
    </div>

    {{-- Indicadores: el color va solo en el icono; el valor en tinta de texto.
         Cada tarjeta enlaza al modulo que la origina (si existe la ruta). --}}
    @php($tiles = [
        ['key' => 'activeTournaments', 'label' => 'Torneos activos', 'icon' => 'fa-trophy', 'tone' => 'bg-amber-100 text-amber-600', 'route' => 'tournaments.index'],
        ['key' => 'activeSeasons', 'label' => 'Temporadas en curso', 'icon' => 'fa-calendar-days', 'tone' => 'bg-indigo-100 text-indigo-600', 'route' => 'seasons.index'],
        ['key' => 'todayMatches', 'label' => 'Partidos de hoy', 'icon' => 'fa-futbol', 'tone' => 'bg-sky-100 text-sky-600', 'route' => 'matches.index'],
        ['key' => 'activeTeams', 'label' => 'Equipos activos', 'icon' => 'fa-people-group', 'tone' => 'bg-emerald-100 text-emerald-600', 'route' => 'teams.index'],
    ])
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ($tiles as $tile)
            @continue($metrics[$tile['key']] === null)
            @php($canLink = Route::has($tile['route']) && auth()->user()->can(match ($tile['route']) {
                'tournaments.index' => 'tournaments.manage', 'seasons.index' => 'seasons.manage',
                'matches.index' => 'matches.manage', default => 'teams.manage' }))
            <a @if ($canLink) href="{{ route($tile['route']) }}" @endif
               class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-900/5 {{ $canLink ? 'hover:ring-indigo-300' : 'pointer-events-none' }}">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $tile['tone'] }}">
                        <i class="fa-solid {{ $tile['icon'] }}"></i>
                    </span>
                    <div>
                        <p class="text-sm text-gray-500">{{ $tile['label'] }}</p>
                        <p class="text-2xl font-semibold tabular-nums text-gray-900">{{ $metrics[$tile['key']] }}</p>
                    </div>
                </div>
            </a>
        @endforeach

        {{-- Estados que requieren accion: icono + etiqueta, nunca solo color. --}}
        @if ($metrics['overdueResults'] !== null)
            <a href="{{ route('results.index') }}" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-900/5 hover:ring-indigo-300">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $metrics['overdueResults'] > 0 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500' }}">
                        <i class="fa-solid {{ $metrics['overdueResults'] > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check' }}"></i>
                    </span>
                    <div>
                        <p class="text-sm text-gray-500">Resultados atrasados</p>
                        <p class="text-2xl font-semibold tabular-nums text-gray-900">{{ $metrics['overdueResults'] }}</p>
                        <p class="text-xs text-gray-400">{{ $metrics['overdueResults'] > 0 ? 'partidos ya jugados sin resultado' : 'al día' }}</p>
                    </div>
                </div>
            </a>
        @endif

        <a @if (Route::has('suspensions.index') && auth()->user()->can('matches.manage')) href="{{ route('suspensions.index') }}" @endif
           class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-900/5">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $metrics['pendingSuspensions'] > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500' }}">
                    <i class="fa-solid fa-user-slash"></i>
                </span>
                <div>
                    <p class="text-sm text-gray-500">Sanciones pendientes</p>
                    <p class="text-2xl font-semibold tabular-nums text-gray-900">{{ $metrics['pendingSuspensions'] }}</p>
                </div>
            </div>
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Proximos partidos --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-900">Próximos partidos <span class="font-normal text-gray-400">(7 días)</span></h2>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($upcoming as $match)
                    <li class="flex items-center justify-between gap-4 px-5 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-gray-900">
                                @if ($this->isHeadToHead($match) && $match->participants->count() === 2)
                                    {{ $this->label($match->participants[0]) }} <span class="text-xs font-normal text-gray-400">vs</span> {{ $this->label($match->participants[1]) }}
                                @else
                                    {{ $match->discipline?->name ?? $match->season->sport->name }} <span class="text-xs font-normal text-gray-400">({{ $match->participants->count() }} participantes)</span>
                                @endif
                            </p>
                            <p class="truncate text-xs text-gray-400">{{ $match->season->name }} · {{ $match->matchday->name }}{{ $match->venue ? ' · '.$match->venue->name : '' }}</p>
                        </div>
                        <span class="shrink-0 text-right text-xs tabular-nums text-gray-600">
                            {{ $match->scheduled_at->isToday() ? 'Hoy' : $match->scheduled_at->translatedFormat('D d/m') }}<br>{{ $match->scheduled_at->format('H:i') }}
                        </span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-gray-400">No hay partidos programados en los próximos 7 días.</li>
                @endforelse
            </ul>
        </div>

        {{-- Ultimos resultados --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-semibold text-gray-900">Últimos resultados</h2>
                @if (Route::has('reports.index'))
                    <a href="{{ route('reports.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">Tablas y medallero →</a>
                @endif
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($latest as $match)
                    <li class="flex items-center justify-between gap-4 px-5 py-3 text-sm">
                        <div class="min-w-0">
                            @if ($this->isHeadToHead($match) && $match->participants->count() === 2)
                                <p class="truncate font-medium text-gray-900">{{ $this->label($match->participants[0]) }} <span class="text-xs font-normal text-gray-400">vs</span> {{ $this->label($match->participants[1]) }}</p>
                            @else
                                @php($winner = $match->participants->firstWhere('position', 1))
                                <p class="truncate font-medium text-gray-900">{{ $match->discipline?->name ?? $match->season->sport->name }}
                                    @if ($winner) <span class="font-normal text-gray-500">— 1° {{ $this->label($winner) }}</span> @endif
                                </p>
                            @endif
                            <p class="truncate text-xs text-gray-400">{{ $match->season->name }} · {{ $match->matchday->name }}</p>
                        </div>
                        <span class="shrink-0 font-mono text-base font-semibold text-gray-900">
                            @if ($match->status === \App\Enums\MatchStatus::Walkover)
                                <span class="text-xs font-sans font-medium text-amber-700"><i class="fa-solid fa-flag"></i> W.O.</span>
                            @elseif ($this->isHeadToHead($match) && $match->participants->count() === 2)
                                {{ (int) $match->participants[0]->result_value }}-{{ (int) $match->participants[1]->result_value }}
                            @endif
                        </span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-gray-400">Todavía no hay resultados cargados.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
