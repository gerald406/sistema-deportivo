<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Sanciones</h1>
            <p class="text-sm text-gray-500">Se generan solas por roja directa o doble amarilla al cerrar un partido; también se cargan a mano.</p>
        </div>

        @can('create', \App\Models\Suspension::class)
            <button wire:click="create" type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-plus"></i> Nueva sanción
            </button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar jugador..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="seasonFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todas las temporadas</option>
            @foreach ($this->seasonOptions() as $season)
                <option value="{{ $season->id }}">{{ $season->tournament->name }} — {{ $season->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="servedFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all">Todas</option>
            <option value="pending">Pendientes</option>
            <option value="served">Cumplidas</option>
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
                        <x-sortable-th field="player_last_name" label="Jugador" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Motivo</th>
                        <x-sortable-th field="matches_suspended" label="Partidos" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Origen</th>
                        <x-sortable-th field="is_served" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="created_at" label="Fecha" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($suspensions as $suspension)
                        <tr wire:key="suspension-{{ $suspension->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $suspension->seasonTeamPlayer->player->last_name }}, {{ $suspension->seasonTeamPlayer->player->first_name }}</p>
                                <p class="text-xs text-gray-400">{{ $suspension->seasonTeamPlayer->seasonTeam->team->name }} · {{ $suspension->season->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $suspension->reason }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $suspension->matches_suspended }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $suspension->match->matchday->name }} <span class="text-xs text-gray-400">(#{{ $suspension->match_id }})</span></td>
                            <td class="px-4 py-3">
                                @can('update', $suspension)
                                    <button wire:click="toggleServed({{ $suspension->id }})" title="Cambiar estado"
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $suspension->is_served ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-700' }}">
                                        {{ $suspension->is_served ? 'Cumplida' : 'Pendiente' }}
                                    </button>
                                @else
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $suspension->is_served ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-700' }}">
                                        {{ $suspension->is_served ? 'Cumplida' : 'Pendiente' }}
                                    </span>
                                @endcan
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $suspension->created_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', $suspension)
                                        <button wire:click="edit({{ $suspension->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    @endcan
                                    @can('delete', $suspension)
                                        <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar"
                                                x-on:click="confirmDelete(@js('sanción de '.$suspension->seasonTeamPlayer->player->last_name), () => $wire.delete({{ $suspension->id }}))">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay sanciones que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $suspensions->links() }}
        </div>
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar sanción' : 'Nueva sanción' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                @if (! $editingId)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Temporada</label>
                        <select wire:model.live="form.season_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona una temporada</option>
                            @foreach ($this->seasonOptions() as $season)
                                <option value="{{ $season->id }}">{{ $season->tournament->name }} — {{ $season->name }}</option>
                            @endforeach
                        </select>
                        @error('form.season_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Partido de origen</label>
                        <select wire:model="form.match_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona...</option>
                            @foreach ($this->matchOptions() as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        @error('form.match_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jugador</label>
                        <select wire:model="form.season_team_player_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona...</option>
                            @foreach ($this->playerOptions() as $entry)
                                <option value="{{ $entry->id }}">{{ $entry->seasonTeam->team->name }} — {{ $entry->player->last_name }}, {{ $entry->player->first_name }}</option>
                            @endforeach
                        </select>
                        @error('form.season_team_player_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Motivo</label>
                        <input type="text" wire:model="form.reason" placeholder="Ej: Conducta antideportiva"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Partidos</label>
                        <input type="number" min="1" max="50" wire:model="form.matches_suspended"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.matches_suspended') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="form.is_served" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Ya cumplida</span>
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
