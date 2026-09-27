<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Planteles</h1>
            <p class="text-sm text-gray-500">Jugadores convocados por cada equipo inscrito en una temporada.</p>
        </div>

        @can('create', \App\Models\SeasonTeamPlayer::class)
            <button wire:click="create" type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-user-plus"></i> Agregar jugador
            </button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar jugador o DNI..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="seasonTeamFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todos los equipos inscritos</option>
            @foreach ($filterSeasonTeams as $st)
                <option value="{{ $st->id }}">{{ $st->team->name }} — {{ $st->season->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="rosterStatusFilter"
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
                        <x-sortable-th field="shirt_number" label="N°" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="player_last_name" label="Jugador" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Equipo / Temporada</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Posición</th>
                        <x-sortable-th field="status" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="enrolled_at" label="Alta" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($entries as $entry)
                        <tr wire:key="roster-{{ $entry->id }}">
                            <td class="px-4 py-3 font-semibold text-gray-700">{{ $entry->shirt_number ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img class="h-8 w-8 rounded-full border object-cover"
                                         src="{{ $entry->player->photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($entry->player->photo_path) : 'https://ui-avatars.com/api/?name='.urlencode($entry->player->fullName()).'&background=6366f1&color=fff' }}"
                                         alt="{{ $entry->player->fullName() }}">
                                    <div>
                                        <p class="font-medium text-gray-900">
                                            {{ $entry->player->last_name }}, {{ $entry->player->first_name }}
                                            @if ($entry->is_captain)
                                                <span class="ml-1 rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold text-white" title="Capitán">C</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-400">DNI {{ $entry->player->dni }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <p>{{ $entry->seasonTeam->team->name }}</p>
                                <p class="text-xs text-gray-400">{{ $entry->seasonTeam->season->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $entry->position?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $entry->status->badgeClasses() }}">
                                    {{ $entry->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $entry->enrolled_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', $entry)
                                        <button wire:click="edit({{ $entry->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    @endcan
                                    @can('delete', $entry)
                                        <button type="button" class="text-gray-400 hover:text-red-600" title="Quitar del plantel"
                                                x-on:click="confirmDelete(@js($entry->player->fullName()), () => $wire.delete({{ $entry->id }}))">
                                            <i class="fa-solid fa-user-minus"></i>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay jugadores que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $entries->links() }}
        </div>
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar ficha de plantel' : 'Agregar jugador al plantel' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                @if ($editingId)
                    @php($player = $this->selectedPlayer())
                    <div class="rounded-lg bg-gray-50 p-3 text-sm">
                        <p class="font-medium text-gray-900">{{ $player?->fullName() }}</p>
                        <p class="text-gray-500">DNI {{ $player?->dni }}</p>
                    </div>
                @else
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Equipo inscrito</label>
                        <select wire:model.live="form.season_team_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona un equipo</option>
                            @foreach ($this->manageableSeasonTeams() as $st)
                                <option value="{{ $st->id }}">{{ $st->team->name }} — {{ $st->season->tournament->name }} / {{ $st->season->name }}</option>
                            @endforeach
                        </select>
                        @error('form.season_team_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jugador</label>
                        @if ($player = $this->selectedPlayer())
                            <div class="mt-1 flex items-center justify-between rounded-lg bg-indigo-50 px-3 py-2 text-sm">
                                <span class="font-medium text-indigo-900">{{ $player->fullName() }} <span class="text-indigo-500">· DNI {{ $player->dni }}</span></span>
                                <button type="button" wire:click="$set('form.player_id', '')" class="text-indigo-400 hover:text-indigo-700" title="Cambiar">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        @else
                            <input type="text" wire:model.live.debounce.300ms="playerSearch" placeholder="Buscar por apellido, nombre o DNI (mín. 2 letras)"
                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @if ($playerSearch !== '')
                                <div class="mt-1 max-h-40 overflow-y-auto rounded-lg border border-gray-200">
                                    @forelse ($this->playerOptions() as $option)
                                        <button type="button" wire:click="selectPlayer({{ $option->id }})" wire:key="opt-{{ $option->id }}"
                                                class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50">
                                            {{ $option->last_name }}, {{ $option->first_name }}
                                            <span class="text-gray-400">· DNI {{ $option->dni }} · {{ $option->birth_date?->format('d/m/Y') }}</span>
                                        </button>
                                    @empty
                                        <p class="px-3 py-2 text-sm text-gray-400">Sin resultados.</p>
                                    @endforelse
                                </div>
                            @endif
                        @endif
                        @error('form.player_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">N° camiseta</label>
                        <input type="number" min="0" max="999" wire:model="form.shirt_number"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.shirt_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Posición</label>
                        <select wire:model="form.position_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Sin posición</option>
                            @foreach ($this->positionOptions() as $position)
                                <option value="{{ $position->id }}">{{ $position->name }}</option>
                            @endforeach
                        </select>
                        @error('form.position_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha de alta</label>
                        <input type="date" wire:model="form.enrolled_at"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.enrolled_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="form.is_captain" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Capitán del equipo</span>
                    </label>
                    @error('form.is_captain') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Observaciones</label>
                    <textarea wire:model="form.observations" rows="2"
                              class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @error('form.observations') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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
        </div>
    </div>
</div>
