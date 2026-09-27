<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Jornadas</h1>
            <p class="text-sm text-gray-500">Fechas o rondas de cada temporada; agrupan los partidos.</p>
        </div>

        @can('create', \App\Models\Matchday::class)
            <button wire:click="create" type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-plus"></i> Nueva jornada
            </button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar jornada..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="seasonFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todas las temporadas</option>
            @foreach ($filterSeasons as $season)
                <option value="{{ $season->id }}">{{ $season->tournament->name }} — {{ $season->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="completionFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all">Todas</option>
            <option value="pending">Por jugar</option>
            <option value="completed">Completadas</option>
        </select>

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
                        <x-sortable-th field="name" label="Jornada" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Temporada</th>
                        <x-sortable-th field="start_date" label="Inicio" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="end_date" label="Fin" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="matches_count" label="Partidos" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="is_completed" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($matchdays as $matchday)
                        <tr wire:key="matchday-{{ $matchday->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $matchday->name }}</p>
                                @if ($matchday->observations)
                                    <p class="max-w-xs truncate text-xs text-gray-400">{{ $matchday->observations }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <p>{{ $matchday->season->name }}</p>
                                <p class="text-xs text-gray-400">{{ $matchday->season->tournament->name }} · {{ $matchday->season->sport->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $matchday->start_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $matchday->end_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $matchday->matches_count }}
                                @if ($matchday->pending_matches_count > 0)
                                    <span class="text-xs text-amber-600">({{ $matchday->pending_matches_count }} por jugar)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                             {{ $matchday->is_completed ? 'bg-gray-100 text-gray-600' : 'bg-sky-100 text-sky-700' }}">
                                    {{ $matchday->is_completed ? 'Completada' : 'Por jugar' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', $matchday)
                                        <button wire:click="edit({{ $matchday->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    @endcan
                                    @can('delete', $matchday)
                                        <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar"
                                                x-on:click="confirmDelete(@js($matchday->name.' — '.$matchday->season->name), () => $wire.delete({{ $matchday->id }}))">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay jornadas que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $matchdays->links() }}
        </div>
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar jornada' : 'Nueva jornada' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Temporada</label>
                    @if ($editingId)
                        {{-- La temporada no se cambia: sus partidos quedarían desalineados. --}}
                        <p class="mt-1 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            {{ \App\Models\Matchday::with('season.tournament')->find($editingId)?->season->name }}
                        </p>
                    @else
                        <select wire:model="form.season_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona una temporada</option>
                            @foreach ($this->seasonOptions() as $season)
                                <option value="{{ $season->id }}">{{ $season->tournament->name }} — {{ $season->name }} ({{ $season->sport->name }})</option>
                            @endforeach
                        </select>
                    @endif
                    @error('form.season_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                    <input type="text" wire:model="form.name" placeholder="Ej: Fecha 1, Cuartos de final"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('form.name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Inicio</label>
                        <input type="date" wire:model="form.start_date"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.start_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fin</label>
                        <input type="date" wire:model="form.end_date"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.end_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Observaciones</label>
                    <textarea wire:model="form.observations" rows="2"
                              class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @error('form.observations') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($editingId)
                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="form.is_completed" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">Jornada completada</span>
                        </label>
                        @error('form.is_completed') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

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
        </div>
    </div>
</div>
