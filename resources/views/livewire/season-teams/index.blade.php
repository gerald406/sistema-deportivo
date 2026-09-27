<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Inscripciones</h1>
            <p class="text-sm text-gray-500">Equipos inscritos en cada temporada.</p>
        </div>

        @can('create', \App\Models\SeasonTeam::class)
            <button wire:click="create" type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-plus"></i> Inscribir equipo
            </button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar equipo..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="seasonFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todas las temporadas</option>
            @foreach ($filterSeasons as $season)
                <option value="{{ $season->id }}">{{ $season->tournament->name }} — {{ $season->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="categoryFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todas las categorías</option>
            @foreach ($filterCategories as $category)
                <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->gender->label() }})</option>
            @endforeach
        </select>

        <select wire:model.live="statusFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all">Todos los estados</option>
            <option value="active">Activas</option>
            <option value="inactive">Inactivas</option>
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
                        <x-sortable-th field="team_name" label="Equipo" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Temporada</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Categoría</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Delegado</th>
                        <x-sortable-th field="roster_count" label="Plantel" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="is_active" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($seasonTeams as $seasonTeam)
                        <tr wire:key="season-team-{{ $seasonTeam->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img class="h-8 w-8 rounded-full border object-cover"
                                         src="{{ $seasonTeam->team->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($seasonTeam->team->logo_path) : 'https://ui-avatars.com/api/?name='.urlencode($seasonTeam->team->name).'&background=6366f1&color=fff' }}"
                                         alt="{{ $seasonTeam->team->name }}">
                                    <span class="font-medium text-gray-900">{{ $seasonTeam->team->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <p>{{ $seasonTeam->season->name }}
                                    <span class="ml-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $seasonTeam->season->status->badgeClasses() }}">{{ $seasonTeam->season->status->label() }}</span>
                                </p>
                                <p class="text-xs text-gray-400">{{ $seasonTeam->season->tournament->name }} · {{ $seasonTeam->season->sport->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $seasonTeam->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $seasonTeam->effectiveDelegate()?->name ?? '—' }}
                                @if ($seasonTeam->delegate_id)
                                    <span class="block text-xs text-gray-400">de temporada</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $seasonTeam->roster_count }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                             {{ $seasonTeam->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $seasonTeam->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', $seasonTeam)
                                        <button wire:click="edit({{ $seasonTeam->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    @endcan
                                    @can('delete', $seasonTeam)
                                        <button type="button" class="text-gray-400 hover:text-red-600" title="Retirar inscripción"
                                                x-on:click="confirmDelete(@js($seasonTeam->team->name.' — '.$seasonTeam->season->name), () => $wire.delete({{ $seasonTeam->id }}))">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay inscripciones que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $seasonTeams->links() }}
        </div>
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar inscripción' : 'Inscribir equipo' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                @if ($editingId)
                    {{-- Temporada y equipo no se cambian: seria otra inscripcion. --}}
                    @php($current = \App\Models\SeasonTeam::with(['team:id,name', 'season:id,name'])->find($editingId))
                    <div class="rounded-lg bg-gray-50 p-3 text-sm">
                        <p class="font-medium text-gray-900">{{ $current?->team->name }}</p>
                        <p class="text-gray-500">{{ $current?->season->name }}</p>
                    </div>
                @else
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Temporada</label>
                        <select wire:model.live="form.season_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona una temporada</option>
                            @foreach ($this->seasonOptions() as $season)
                                <option value="{{ $season->id }}">{{ $season->tournament->name }} — {{ $season->name }} ({{ $season->status->label() }})</option>
                            @endforeach
                        </select>
                        @error('form.season_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Equipo</label>
                        <select wire:model="form.team_id" @disabled($form['season_id'] === '')
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                            <option value="">{{ $form['season_id'] === '' ? 'Primero elige la temporada' : 'Selecciona un equipo' }}</option>
                            @foreach ($this->teamOptions() as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                        @error('form.team_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700">Categoría <span class="text-gray-400">(opcional)</span></label>
                    <select wire:model="form.category_id"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Sin categoría</option>
                        @foreach ($this->categoryOptions() as $category)
                            <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->gender->label() }})</option>
                        @endforeach
                    </select>
                    @error('form.category_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($this->canChooseDelegate())
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Delegado de temporada <span class="text-gray-400">(opcional)</span></label>
                        <select wire:model="form.delegate_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Usar el delegado general del equipo</option>
                            @foreach ($this->delegateOptions() as $delegate)
                                <option value="{{ $delegate->id }}">{{ $delegate->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Si se asigna, reemplaza al delegado general del equipo en esta temporada.</p>
                        @error('form.delegate_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

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
