<div class="mx-auto max-w-6xl space-y-6">

    <div>
        <h1 class="flex items-center gap-2 text-2xl font-semibold text-gray-900">
            <i class="fa-solid fa-file-signature text-indigo-600"></i>
            Fichas de Inscripción
        </h1>
        <p class="mt-1 text-sm text-gray-500">Selecciona participantes del plantel de una competencia para generar sus fichas de inscripción en PDF.</p>
    </div>

    <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-md sm:p-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Olimpiada</label>
                <select wire:model.live="gamesEditionId" class="w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm shadow-sm">
                    <option value="">-- Seleccione --</option>
                    @foreach ($this->gamesEditions() as $gamesEdition)
                        <option value="{{ $gamesEdition->id }}">{{ $gamesEdition->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Competencia</label>
                <select wire:model.live="seasonId" class="w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm shadow-sm disabled:bg-gray-100" @disabled($gamesEditionId === '')>
                    <option value="">-- Seleccione --</option>
                    @foreach ($this->seasons() as $season)
                        <option value="{{ $season->id }}">{{ $season->name }} ({{ $season->status->label() }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Equipo</label>
                <select wire:model.live="seasonTeamId" class="w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm shadow-sm disabled:bg-gray-100" @disabled($seasonId === '')>
                    <option value="">-- Todos los equipos --</option>
                    @foreach ($this->seasonTeams() as $seasonTeam)
                        <option value="{{ $seasonTeam->id }}">{{ $seasonTeam->team->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Buscar participante</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Nombre, apellido o documento"
                       class="w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm shadow-sm">
            </div>
        </div>
    </div>

    @if ($seasonId !== '')
        {{-- POST a una pestaña nueva con los ids elegidos (mismo patrón que la referencia):
             el PDF no pasa por Livewire y la URL no lleva la lista de ids. --}}
        <form method="POST" action="{{ route('registrations.massive.post') }}" target="_blank" x-data>
            @csrf
            <input type="hidden" name="ids" :value="$wire.selected.join(',')">

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-md">
                <div class="flex flex-col gap-3 border-b bg-gray-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input type="checkbox" wire:change="toggleAll($event.target.checked)" class="rounded border-gray-300"
                               @checked($entries->isNotEmpty() && count($selected) >= min($entries->count(), \App\Livewire\PlayerRegistrationSelector::MAX_SELECTION))>
                        Seleccionar todos ({{ $entries->count() }})
                    </label>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($seasonTeamId !== '')
                            <a href="{{ route('registrations.team', (int) $seasonTeamId) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 rounded-lg border border-indigo-300 bg-white px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                                <i class="fa-solid fa-users"></i> Todo el equipo
                            </a>
                        @endif
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="$wire.selected.length === 0">
                            <i class="fa-solid fa-file-pdf"></i>
                            Descargar fichas (<span x-text="$wire.selected.length"></span>)
                        </button>
                    </div>
                </div>

                <div class="max-h-[28rem] divide-y overflow-y-auto">
                    @forelse ($entries as $entry)
                        <label wire:key="reg-{{ $entry->id }}" class="flex cursor-pointer items-center justify-between p-3 hover:bg-gray-50">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" wire:model.live="selected" value="{{ $entry->id }}" class="rounded border-gray-300">
                                <div>
                                    <p class="text-sm font-bold text-gray-900">{{ $entry->player->last_name }}, {{ $entry->player->first_name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $entry->seasonTeam->team->name }} · Doc. {{ $entry->player->dni }}
                                        @if ($entry->shirt_number !== null) · #{{ $entry->shirt_number }} @endif
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('registrations.single', $entry->id) }}" target="_blank" rel="noopener"
                               class="text-xs font-bold text-indigo-600 hover:text-indigo-800" x-on:click.stop>
                                <i class="fa-solid fa-file-pdf mr-1"></i> Individual
                            </a>
                        </label>
                    @empty
                        <p class="py-8 text-center text-sm text-gray-500">No hay participantes para los filtros seleccionados.</p>
                    @endforelse
                </div>
            </div>
        </form>
    @else
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center text-gray-400 shadow-md">
            <i class="fa-solid fa-trophy mb-3 text-3xl"></i>
            <p>Selecciona una olimpiada y una competencia para ver los participantes.</p>
        </div>
    @endif

</div>
