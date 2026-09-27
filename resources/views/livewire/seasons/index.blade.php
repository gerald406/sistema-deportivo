<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Temporadas</h1>
            <p class="text-sm text-gray-500">Cada temporada es un deporte dentro de un torneo, con sus fases de puntaje.</p>
        </div>

        @can('create', \App\Models\Season::class)
            <button wire:click="create" type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-plus"></i> Nueva temporada
            </button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar por nombre..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="tournamentFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todos los torneos</option>
            @foreach ($this->tournamentFilterOptions() as $tournament)
                <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="sportFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todos los deportes</option>
            @foreach ($filterSports as $sport)
                <option value="{{ $sport->id }}">{{ $sport->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="seasonStatusFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all">Todos los estados</option>
            @foreach ($this->statusOptions() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
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
                        <x-sortable-th field="name" label="Temporada" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Torneo / Deporte</th>
                        <x-sortable-th field="start_date" label="Inicio" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="end_date" label="Fin" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="season_teams_count" label="Equipos" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="status" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="is_active" label="Activa" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($seasons as $season)
                        <tr wire:key="season-{{ $season->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $season->name }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ $season->scoring_configs_count }} {{ Str::plural('fase', $season->scoring_configs_count) }} de puntaje
                                    @if ($season->gamesEdition)
                                        · <i class="fa-solid fa-medal"></i> {{ $season->gamesEdition->name }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <p>{{ $season->tournament->name }}</p>
                                <p class="text-xs text-gray-400">{{ $season->sport->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $season->start_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $season->end_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $season->season_teams_count }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $season->status->badgeClasses() }}">
                                    {{ $season->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                             {{ $season->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $season->is_active ? 'Sí' : 'No' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', $season)
                                        <button wire:click="edit({{ $season->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    @endcan
                                    @can('delete', $season)
                                        <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar"
                                                x-on:click="confirmDelete(@js($season->name), () => $wire.delete({{ $season->id }}))">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400">No hay temporadas que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $seasons->links() }}
        </div>
    </div>

    {{-- Modal crear/editar (Season + sub-formulario de fases de puntaje) --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar temporada' : 'Nueva temporada' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Torneo</label>
                        <select wire:model="form.tournament_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona un torneo</option>
                            @foreach ($this->tournamentOptions() as $tournament)
                                <option value="{{ $tournament->id }}">{{ $tournament->name }}{{ $tournament->is_active ? '' : ' (inactivo)' }}</option>
                            @endforeach
                        </select>
                        @error('form.tournament_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Deporte</label>
                        <select wire:model="form.sport_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona un deporte</option>
                            @foreach ($this->sportOptions() as $sport)
                                <option value="{{ $sport->id }}">{{ $sport->name }}</option>
                            @endforeach
                        </select>
                        @error('form.sport_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                    <input type="text" wire:model="form.name" placeholder="Ej: Apertura 2027"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('form.name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estado</label>
                        <select wire:model="form.status"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($this->statusOptions() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('form.status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Edición olímpica <span class="text-gray-400">(opcional)</span></label>
                    <select wire:model="form.games_edition_id"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">No pertenece a una edición</option>
                        @foreach ($this->editionOptions() as $edition)
                            <option value="{{ $edition->id }}">{{ $edition->name }}</option>
                        @endforeach
                    </select>
                    @error('form.games_edition_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Sub-formulario: fases de puntaje (ScoringConfig) --}}
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Fases de puntaje</p>
                            <p class="text-xs text-gray-500">Puntos que suma un equipo por resultado en cada fase.</p>
                        </div>
                        <button type="button" wire:click="addPhase"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">
                            + Agregar fase
                        </button>
                    </div>
                    @error('phases') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

                    <div class="mt-3 space-y-3">
                        @foreach ($phases as $i => $phase)
                            <div wire:key="phase-{{ $phase['id'] ?? 'new' }}-{{ $i }}" class="rounded-lg bg-white p-3 ring-1 ring-gray-200">
                                <div class="flex items-center gap-2">
                                    <input type="text" wire:model="phases.{{ $i }}.phase_name" placeholder="Nombre de la fase (ej: Liguilla)"
                                           class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @if (count($phases) > 1)
                                        <button type="button" wire:click="removePhase({{ $i }})" class="text-gray-400 hover:text-red-600" title="Quitar fase">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    @endif
                                </div>
                                @error("phases.$i.phase_name") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    @foreach (['pts_win' => 'Victoria', 'pts_draw' => 'Empate', 'pts_loss' => 'Derrota', 'pts_walkover' => 'Walkover'] as $field => $label)
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500">{{ $label }}</label>
                                            <input type="number" min="-99" max="99" wire:model="phases.{{ $i }}.{{ $field }}"
                                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @error("phases.$i.$field") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="form.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Activa</span>
                </label>

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
