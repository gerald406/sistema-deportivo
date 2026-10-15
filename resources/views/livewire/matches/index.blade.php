<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Partidos</h1>
            <p class="text-sm text-gray-500">Los partidos nacen al generar la llave en Fases; aquí se programan: jornada, fecha y hora, sede y estado.</p>
        </div>

        @if (auth()->user()->hasRole('admin') || auth()->user()->can('matches.manage'))
            <a href="{{ route('season-stages.index') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
                <i class="fa-solid fa-sitemap"></i> Generar llaves en Fases
            </a>
        @endif
    </div>

    {{-- Filtros --}}
    {{-- Cuadricula: el select de competencias (olimpiada — competencia) desbordaba en fila. --}}
    <div class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:grid-cols-2 xl:grid-cols-6">
        <div class="relative min-w-0 sm:col-span-2 xl:col-span-2">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar equipo o participante..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="seasonFilter" title="Competencia"
                class="w-full min-w-0 truncate rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 sm:col-span-2 xl:col-span-2">
            <option value="">Todas las competencias</option>
            @foreach ($filterSeasons->groupBy(fn ($s) => $s->gamesEdition->name) as $editionName => $group)
                <optgroup label="{{ \Illuminate\Support\Str::limit($editionName, 60) }}">
                    @foreach ($group as $season)
                        <option value="{{ $season->id }}">{{ $season->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>

        <select wire:model.live="matchdayFilter" @disabled($filterMatchdays->isEmpty()) title="Jornada"
                class="w-full min-w-0 truncate rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 xl:col-span-1">
            <option value="">Todas las jornadas</option>
            @foreach ($filterMatchdays as $matchday)
                <option value="{{ $matchday->id }}">{{ $matchday->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="matchStatusFilter" title="Estado"
                class="w-full min-w-0 rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 xl:col-span-1">
            <option value="all">Todos los estados</option>
            @foreach ($statusOptions as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>

        <div class="flex min-w-0 gap-3 sm:col-span-2 xl:col-span-2 xl:col-start-5">
            <select wire:model.live="perPage" title="Filas por página"
                    class="w-full min-w-0 flex-1 rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="10">10 por página</option>
                <option value="15">15 por página</option>
                <option value="25">25 por página</option>
                <option value="50">50 por página</option>
            </select>
            <x-export-button />
        </div>
    </div>

    {{-- Tabla --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <x-sortable-th field="scheduled_at" label="Fecha" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="participants_count" label="Participantes" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Competencia / Jornada</th>
                        <x-sortable-th field="round_type" label="Ronda" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Sede</th>
                        <x-sortable-th field="status" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($matches as $match)
                        @php($format = $match->discipline?->format_type ?? $match->season->sport->format_type)
                        <tr wire:key="match-{{ $match->id }}">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                {{ $match->scheduled_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($format === \App\Enums\SportFormatType::HeadToHead && $match->participants->count() === 2)
                                    <p class="font-medium text-gray-900">
                                        {{ $this->participantLabel($match->participants[0]) }}
                                        <span class="px-1 text-xs font-normal text-gray-400">vs</span>
                                        {{ $this->participantLabel($match->participants[1]) }}
                                    </p>
                                @else
                                    <p class="font-medium text-gray-900">{{ $match->discipline?->name ?? $match->season->sport->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $match->participants_count }} {{ Str::plural('participante', $match->participants_count) }}</p>
                                @endif
                                @if ($match->discipline && $format === \App\Enums\SportFormatType::HeadToHead)
                                    <p class="text-xs text-gray-400">{{ $match->discipline->name }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <p>{{ $match->season->name }} · {{ $match->matchday->name }}</p>
                                <p class="text-xs text-gray-400">{{ $match->season->gamesEdition->name }} · {{ $match->season->sport->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $match->round_type->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $match->venue?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $match->status->badgeClasses() }}">
                                    {{ $match->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', $match)
                                        <button wire:click="edit({{ $match->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    @endcan
                                    @can('delete', $match)
                                        <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar"
                                                x-on:click="confirmDelete(@js('Partido #'.$match->id.' — '.$match->matchday->name), () => $wire.delete({{ $match->id }}))">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay partidos que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $matches->links() }}
        </div>
    </div>

    {{-- Modal programar (D24): competencia, ronda y participantes vienen de la llave y solo se muestran --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">Programar partido</h2>

            @if ($showModal && ($match = $this->editingMatch()))
                {{-- Lo que viene de la llave: no se edita aqui --}}
                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm">
                    <p class="text-xs text-gray-500">{{ $match->season->gamesEdition->name }}</p>
                    <p class="font-medium text-gray-800">
                        {{ $match->season->name }}{{ $match->discipline ? ' — '.$match->discipline->name : '' }}
                    </p>
                    <p class="text-xs text-gray-500">
                        {{ $match->stage?->name ? $match->stage->name.' · ' : '' }}{{ $match->round_type->label() }}
                    </p>
                    <p class="mt-2 font-semibold text-gray-900">
                        @if ($match->participants->count() === 2 && $match->participants->first()->side)
                            {{ $this->participantLabel($match->participants[0]) }}
                            <span class="px-1 text-xs font-normal text-gray-400">vs</span>
                            {{ $this->participantLabel($match->participants[1]) }}
                        @elseif ($match->participants->isEmpty())
                            <span class="font-normal text-gray-400">Por definir (lo completa la llave)</span>
                        @else
                            {{ $match->participants->map(fn ($p) => $this->participantLabel($p))->join(', ') }}
                        @endif
                    </p>
                </div>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Jornada</label>
                            <select wire:model="form.matchday_id"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Selecciona una jornada</option>
                                @foreach ($this->matchdayOptions() as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}{{ $option->is_completed ? ' (completada)' : '' }}</option>
                                @endforeach
                            </select>
                            @error('form.matchday_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha y hora</label>
                            <input type="datetime-local" wire:model="form.scheduled_at"
                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('form.scheduled_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sede</label>
                            <select wire:model="form.venue_id"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Sin sede</option>
                                @foreach ($this->venueOptions() as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                            @error('form.venue_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Estado</label>
                            @if (in_array($form['status'], ['pending', 'canceled'], true))
                                <select wire:model="form.status"
                                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="pending">Programado</option>
                                    <option value="canceled">Cancelado</option>
                                </select>
                            @else
                                {{-- Jugado / Walkover: lo gestiona la carga de resultados --}}
                                <p class="mt-1 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ \App\Enums\MatchStatus::from($form['status'])->label() }}</p>
                            @endif
                            @error('form.status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                            <span wire:loading.remove wire:target="save">Guardar</span>
                            <span wire:loading wire:target="save"><i class="fa-solid fa-spinner fa-spin"></i> Guardando...</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
