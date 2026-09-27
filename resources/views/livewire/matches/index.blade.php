<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Partidos</h1>
            <p class="text-sm text-gray-500">Enfrentamientos, pruebas individuales y postas, con sus participantes.</p>
        </div>

        @can('create', \App\Models\GameMatch::class)
            <button wire:click="create" type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-plus"></i> Programar partido
            </button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar equipo o jugador..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="seasonFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todas las temporadas</option>
            @foreach ($filterSeasons as $season)
                <option value="{{ $season->id }}">{{ $season->tournament->name }} — {{ $season->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="matchdayFilter" @disabled($filterMatchdays->isEmpty())
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
            <option value="">Todas las jornadas</option>
            @foreach ($filterMatchdays as $matchday)
                <option value="{{ $matchday->id }}">{{ $matchday->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="matchStatusFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all">Todos los estados</option>
            @foreach ($statusOptions as $option)
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
                        <x-sortable-th field="scheduled_at" label="Fecha" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="participants_count" label="Participantes" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Temporada / Jornada</th>
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
                                <p class="text-xs text-gray-400">{{ $match->season->tournament->name }} · {{ $match->season->sport->name }}</p>
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

    {{-- Modal crear/editar (partido + participantes) --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar partido' : 'Programar partido' }}
            </h2>

            @php($season = $this->formSeason())
            @php($format = $this->format())

            <form wire:submit="save" class="mt-4 space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Temporada</label>
                        @if ($editingId)
                            <p class="mt-1 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $season?->tournament->name }} — {{ $season?->name }}</p>
                        @else
                            <select wire:model.live="form.season_id"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Selecciona una temporada</option>
                                @foreach ($this->seasonOptions() as $option)
                                    <option value="{{ $option->id }}">{{ $option->tournament->name }} — {{ $option->name }} ({{ $option->sport->name }})</option>
                                @endforeach
                            </select>
                        @endif
                        @error('form.season_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jornada</label>
                        <select wire:model="form.matchday_id" @disabled(! $season)
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                            <option value="">{{ $season ? 'Selecciona una jornada' : 'Primero elige la temporada' }}</option>
                            @foreach ($this->matchdayOptions() as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}{{ $option->is_completed ? ' (completada)' : '' }}</option>
                            @endforeach
                        </select>
                        @error('form.matchday_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($season && $this->disciplineOptions()->isNotEmpty())
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Disciplina</label>
                        <select wire:model.live="form.discipline_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona una disciplina</option>
                            @foreach ($this->disciplineOptions() as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                        @error('form.discipline_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @else
                    @error('form.discipline_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                @endif

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha y hora</label>
                        <input type="datetime-local" wire:model="form.scheduled_at"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.scheduled_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Ronda</label>
                        <select wire:model="form.round_type"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($this->roundOptions() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
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
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Modalidad <span class="text-gray-400">(opcional)</span></label>
                        <select wire:model="form.modality"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">—</option>
                            @foreach ($this->modalityOptions() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($editingId && in_array($form['status'], ['pending', 'canceled'], true))
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Estado</label>
                            <select wire:model="form.status"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="pending">Programado</option>
                                <option value="canceled">Cancelado</option>
                            </select>
                            @error('form.status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                {{-- Participantes: dependen del formato efectivo (disciplina ?? deporte) --}}
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-700">Participantes</p>
                        @if ($format)
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs text-gray-500 ring-1 ring-gray-200">{{ $format->label() }}</span>
                        @endif
                    </div>
                    @error('participants') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

                    @if (! $format)
                        <p class="mt-2 text-sm text-gray-400">Elige la temporada{{ $season && $this->disciplineOptions()->isNotEmpty() ? ' y la disciplina' : '' }} para asignar participantes.</p>
                    @elseif ($format === \App\Enums\SportFormatType::HeadToHead)
                        <div class="mt-3 flex gap-4 text-sm">
                            <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="participantMode" value="teams" class="text-indigo-600"> Equipos</label>
                            <label class="flex items-center gap-1.5"><input type="radio" wire:model.live="participantMode" value="players" class="text-indigo-600"> Jugadores</label>
                        </div>
                        @php($options = $participantMode === 'players' ? $this->playerOptions() : $this->teamOptions())
                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach (['homeId' => $participantMode === 'players' ? 'Local / blancas' : 'Local', 'awayId' => $participantMode === 'players' ? 'Visitante / negras' : 'Visitante'] as $field => $label)
                                <div>
                                    <label class="block text-xs font-medium text-gray-500">{{ $label }}</label>
                                    <select wire:model="{{ $field }}"
                                            class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Selecciona...</option>
                                        @foreach ($options as $option)
                                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    @else
                        @php($options = $format === \App\Enums\SportFormatType::TeamRelay ? $this->teamOptions() : $this->playerOptions())
                        <div class="mt-3 space-y-2">
                            @foreach ($entries as $i => $entry)
                                <div wire:key="entry-{{ $i }}" class="flex items-center gap-2">
                                    <select wire:model="entries.{{ $i }}.id"
                                            class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ $format === \App\Enums\SportFormatType::TeamRelay ? 'Equipo...' : 'Jugador...' }}</option>
                                        @foreach ($options as $option)
                                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" wire:model="entries.{{ $i }}.lane" placeholder="Carril"
                                           class="w-24 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <button type="button" wire:click="removeEntry({{ $i }})" class="text-gray-400 hover:text-red-600" title="Quitar">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" wire:click="addEntry" class="mt-2 text-xs font-semibold text-indigo-600 hover:text-indigo-500">
                            + Agregar {{ $format === \App\Enums\SportFormatType::TeamRelay ? 'equipo' : 'participante' }}
                        </button>
                    @endif
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
