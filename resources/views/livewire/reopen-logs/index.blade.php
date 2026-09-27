<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Reaperturas de partidos</h1>
        <p class="text-sm text-gray-500">Auditoría de solo lectura: quién reabrió cada resultado, cuándo, por qué y qué había antes.</p>
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar en el motivo..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="seasonFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todas las temporadas</option>
            @foreach ($filterSeasons as $season)
                <option value="{{ $season->id }}">{{ $season->tournament->name }} — {{ $season->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="userFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todos los usuarios</option>
            @foreach ($this->userOptions() as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
            @endforeach
        </select>

        <input type="date" wire:model.live="dateFrom" title="Desde"
               class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <input type="date" wire:model.live="dateTo" title="Hasta"
               class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">

        <select wire:model.live="perPage"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="10">10 por página</option>
            <option value="15">15 por página</option>
            <option value="25">25 por página</option>
            <option value="50">50 por página</option>
        </select>
    </div>

    {{-- Tabla --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <x-sortable-th field="created_at" label="Fecha" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Partido</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Usuario</th>
                        <x-sortable-th field="previous_status" label="Estado previo" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="suspensions_deleted" label="Sanciones borradas" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Motivo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr wire:key="log-{{ $log->id }}">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">
                                @if ($log->match)
                                    <p class="font-medium text-gray-900">{{ $log->match->participants->map(fn ($p) => \App\Support\ParticipantLabel::for($p))->join(' vs ') }}</p>
                                    <p class="text-xs text-gray-400">{{ $log->match->season->tournament->name }} · {{ $log->match->season->name }} · {{ $log->match->matchday->name }}</p>
                                @else
                                    <span class="text-gray-400">(partido eliminado)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $log->user->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ \App\Enums\MatchStatus::tryFrom($log->previous_status)?->label() ?? $log->previous_status }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $log->suspensions_deleted }}</td>
                            <td class="max-w-xs truncate px-4 py-3 text-gray-600" title="{{ $log->reason }}">{{ $log->reason }}</td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="show({{ $log->id }})" class="text-gray-400 hover:text-indigo-600" title="Ver detalle">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay reaperturas registradas con ese filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $logs->links() }}
        </div>
    </div>

    {{-- Detalle (solo lectura) --}}
    @if ($detail = $this->viewing())
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div wire:click="closeDetail" class="fixed inset-0 bg-gray-900/50"></div>
            <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Reapertura #{{ $detail->id }}</h2>
                        <p class="text-sm text-gray-500">{{ $detail->created_at->format('d/m/Y H:i') }} · {{ $detail->user->name }}</p>
                    </div>
                    <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    <div><dt class="font-medium text-gray-700">Motivo</dt><dd class="text-gray-600">{{ $detail->reason ?? '—' }}</dd></div>
                    <div><dt class="font-medium text-gray-700">Estado previo</dt><dd class="text-gray-600">{{ \App\Enums\MatchStatus::tryFrom($detail->previous_status)?->label() ?? $detail->previous_status }}</dd></div>
                    <div><dt class="font-medium text-gray-700">Efectos</dt><dd class="text-gray-600">{{ $detail->suspensions_deleted }} sanción(es) pendiente(s) borrada(s); {{ $detail->events_deleted }} incidencia(s) borrada(s).</dd></div>
                </dl>

                <p class="mt-4 text-sm font-medium text-gray-700">Resultado anterior</p>
                <table class="mt-1 w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-gray-400"><th class="py-1">Participante</th><th>Lado</th><th>Marca</th><th>Puesto</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($this->snapshotParticipants($detail) as $p)
                            <tr>
                                <td class="py-1 text-gray-800">{{ $p['label'] }}</td>
                                <td class="text-gray-500">{{ ['home' => 'Local', 'away' => 'Visitante'][$p['side']] ?? '—' }}</td>
                                <td class="font-mono text-gray-700">{{ $p['result'] !== null ? rtrim(rtrim((string) $p['result'], '0'), '.') : '—' }}</td>
                                <td class="text-gray-700">{{ $p['position'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if (! empty($detail->previous_state['periods']))
                    <p class="mt-3 text-sm font-medium text-gray-700">Parciales</p>
                    <p class="font-mono text-sm text-gray-600">
                        @foreach ($detail->previous_state['periods'] as [$n, $h, $a])
                            <span class="mr-3">P{{ $n }} {{ $h }}-{{ $a }}</span>
                        @endforeach
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>
