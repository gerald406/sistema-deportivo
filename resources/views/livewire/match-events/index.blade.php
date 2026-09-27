<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Incidencias</h1>
            <p class="text-sm text-gray-500">Goles, tarjetas y demás eventos de cada partido. Se cargan antes de cerrar el resultado.</p>
        </div>

        @can('create', \App\Models\MatchEvent::class)
            <button wire:click="create" type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-plus"></i> Nueva incidencia
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
            @foreach ($filterSeasons as $season)
                <option value="{{ $season->id }}">{{ $season->tournament->name }} — {{ $season->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="eventTypeFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todos los tipos</option>
            @foreach ($filterEventTypes as $type)
                <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->sport->name }})</option>
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
                        <x-sortable-th field="minute" label="Min." :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Incidencia</th>
                        <x-sortable-th field="player_last_name" label="Jugador" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Partido</th>
                        <x-sortable-th field="created_at" label="Registrada" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($events as $event)
                        <tr wire:key="event-{{ $event->id }}">
                            <td class="px-4 py-3 font-mono text-gray-600">{{ $event->minute !== null ? $event->minute."'" : '—' }}</td>
                            <td class="px-4 py-3">
                                @php($icon = match ($event->eventType->code) {
                                    'goal' => 'fa-futbol text-gray-700', 'yellow_card' => 'fa-square text-yellow-400',
                                    'red_card', 'red_card_indirect' => 'fa-square text-red-600', default => 'fa-circle-dot text-indigo-500',
                                })
                                <i class="fa-solid {{ $icon }} mr-1"></i> {{ $event->eventType->name }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $event->seasonTeamPlayer->player->last_name }}, {{ $event->seasonTeamPlayer->player->first_name }}</p>
                                <p class="text-xs text-gray-400">{{ $event->seasonTeamPlayer->seasonTeam->team->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <p>{{ $this->matchLabel($event->match) }}</p>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $event->match->status->badgeClasses() }}">{{ $event->match->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $event->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($event->match->status === \App\Enums\MatchStatus::Pending)
                                    <div class="flex items-center justify-end gap-3">
                                        @can('update', $event)
                                            <button wire:click="edit({{ $event->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                        @endcan
                                        @can('delete', $event)
                                            <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar"
                                                    x-on:click="confirmDelete(@js($event->eventType->name.' de '.$event->seasonTeamPlayer->player->last_name), () => $wire.delete({{ $event->id }}))">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        @endcan
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">No hay incidencias que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $events->links() }}
        </div>
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar incidencia' : 'Nueva incidencia' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Partido</label>
                    @if ($editingId)
                        @php($current = $this->formMatch()?->load(['matchday', 'participants' => \App\Support\ParticipantLabel::eagerLoad()]))
                        <p class="mt-1 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $current ? $this->matchLabel($current) : '' }}</p>
                    @else
                        <select wire:model.live="form.match_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona un partido programado</option>
                            @foreach ($this->matchOptions() as $option)
                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('form.match_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo</label>
                        <select wire:model="form.event_type_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona...</option>
                            @foreach ($this->eventTypeOptions() as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('form.event_type_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Minuto <span class="text-gray-400">(opcional)</span></label>
                        <input type="number" min="0" max="200" wire:model="form.minute"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.minute') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Jugador</label>
                    <select wire:model="form.season_team_player_id"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Selecciona...</option>
                        @foreach ($this->playerOptions() as $entry)
                            <option value="{{ $entry->id }}">{{ $entry->seasonTeam->team->name }} — {{ $entry->player->last_name }}, {{ $entry->player->first_name }}{{ $entry->shirt_number ? ' (#'.$entry->shirt_number.')' : '' }}</option>
                        @endforeach
                    </select>
                    @error('form.season_team_player_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="closeModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                        {{ $editingId ? 'Cancelar' : 'Cerrar' }}
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
