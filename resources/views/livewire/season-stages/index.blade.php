<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Fases y llaves</h1>
            <p class="text-sm text-gray-500">Cómo se juega cada competencia: series y fase final con partidos, o podio directo (solo 1.º, 2.º y 3.º).</p>
        </div>

        @if ($season)
            <div class="flex flex-wrap gap-2">
                @if ($stages->isEmpty())
                    <button wire:click="createStandard" type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Crear fases estándar
                    </button>
                @endif
                <button wire:click="create" type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    <i class="fa-solid fa-plus"></i> Nueva fase
                </button>
            </div>
        @endif
    </div>

    {{-- Selector de competencia --}}
    <div class="flex flex-col gap-2 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:flex-row sm:items-center">
        <label for="stage-season" class="text-sm font-medium text-gray-700">
            <i class="fa-solid fa-medal mr-1 text-amber-500"></i> Competencia
        </label>
        <select id="stage-season" wire:model.live="seasonId"
                class="flex-1 rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Selecciona una competencia</option>
            @foreach ($this->seasonOptions()->groupBy(fn ($s) => $s->gamesEdition->name) as $editionName => $group)
                <optgroup label="{{ $editionName }}">
                    @foreach ($group as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>

    @if (! $season)
        <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
            Elige una competencia para ver sus fases.
        </div>
    @else
        <div class="space-y-4">
            @forelse ($stages as $stage)
                <div wire:key="stage-{{ $stage->id }}" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-900/5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ $stage->name }}</h2>
                            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                <span class="rounded-full px-2 py-0.5 font-medium {{ $stage->mode === \App\Enums\StageMode::Matches ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $stage->mode->label() }}
                                </span>
                                @if ($stage->group)
                                    <span>Serie: {{ $stage->group->name }}</span>
                                @endif
                                @if ($stage->qualifiers)
                                    <span>Clasifica{{ $stage->qualifiers === 1 ? '' : 'n' }}: {{ $stage->qualifiers }}</span>
                                @endif
                                @if ($stage->has_third_place)
                                    <span>Con partido por el tercer puesto</span>
                                @endif
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            @if ($stage->mode === \App\Enums\StageMode::DirectPodium)
                                <button wire:click="openPodium({{ $stage->id }})" type="button"
                                        class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-400">
                                    <i class="fa-solid fa-trophy"></i> {{ $stage->results->isEmpty() ? 'Registrar podio' : 'Editar podio' }}
                                </button>
                            @endif
                            <button wire:click="edit({{ $stage->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar fase">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar fase"
                                    x-on:click="confirmDelete(@js($stage->name), () => $wire.delete({{ $stage->id }}))">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    @if ($stage->mode === \App\Enums\StageMode::DirectPodium)
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            @foreach ([1 => ['1.º puesto', 'border-amber-300 bg-amber-50'], 2 => ['2.º puesto', 'border-gray-300 bg-gray-50'], 3 => ['3.º puesto', 'border-orange-300 bg-orange-50']] as $place => [$label, $classes])
                                <div class="rounded-lg border p-3 {{ $classes }}">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                                    @forelse ($stage->results->where('place', $place) as $result)
                                        <p class="mt-1 text-sm font-medium text-gray-900">{{ $result->team->name }}</p>
                                        @if ($result->seasonTeamPlayer)
                                            <p class="text-xs text-gray-500">{{ $result->seasonTeamPlayer->player->fullName() }}</p>
                                        @endif
                                    @empty
                                        <p class="mt-1 text-sm text-gray-400">Sin registrar</p>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
                    Esta competencia aún no tiene fases. «Crear fases estándar» las arma según su deporte.
                </div>
            @endforelse
        </div>
    @endif

    {{-- Modal crear/editar fase --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Editar fase' : 'Nueva fase' }}</h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" wire:model="form.name" placeholder="Ej: Serie A, Fase final"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Orden</label>
                        <input type="number" wire:model="form.sort_order" min="0" max="999"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.sort_order') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Cómo se juega</label>
                    <select wire:model.live="form.mode"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($this->modeOptions() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('form.mode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($form['mode'] === \App\Enums\StageMode::Matches->value)
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Serie <span class="font-normal text-gray-400">(opcional)</span></label>
                            <select wire:model="form.group_id"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Ninguna</option>
                                @foreach ($this->groupOptions() as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                            @error('form.group_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Clasificados</label>
                            <input type="number" wire:model="form.qualifiers" min="1" max="64" placeholder="—"
                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('form.qualifiers') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="form.has_third_place" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Partido por el tercer puesto</span>
                    </label>
                @endif

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="closeModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cancelar</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        <span wire:loading.remove wire:target="save">Guardar</span>
                        <span wire:loading wire:target="save"><i class="fa-solid fa-spinner fa-spin"></i> Guardando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal podio directo --}}
    <div x-data="{ show: @entangle('showPodium') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">Podio</h2>
            <p class="text-sm text-gray-500">Elige la delegación de cada puesto. El participante es opcional. Agrega otra fila si el puesto es compartido.</p>

            <form wire:submit="savePodium" class="mt-4 space-y-4">
                @error('podium') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror

                @php($teamOptions = $showPodium ? $this->teamOptions() : collect())
                @foreach ([1 => '1.º puesto', 2 => '2.º puesto', 3 => '3.º puesto'] as $place => $label)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-700">{{ $label }}</p>
                            <button type="button" wire:click="addPodiumRow({{ $place }})" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">
                                + Puesto compartido
                            </button>
                        </div>

                        @foreach ($podium[$place] ?? [] as $i => $row)
                            <div wire:key="podium-{{ $place }}-{{ $i }}" class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                                <select wire:model.live="podium.{{ $place }}.{{ $i }}.team_id"
                                        class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Delegación —</option>
                                    @foreach ($teamOptions as $team)
                                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                                    @endforeach
                                </select>
                                <select wire:model="podium.{{ $place }}.{{ $i }}.season_team_player_id"
                                        class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Participante (opcional) —</option>
                                    @foreach ($this->rosterOptions($row['team_id'] ?? null) as $entry)
                                        <option value="{{ $entry->id }}">{{ $entry->player->last_name }}, {{ $entry->player->first_name }}</option>
                                    @endforeach
                                </select>
                                @if (count($podium[$place]) > 1)
                                    <button type="button" wire:click="removePodiumRow({{ $place }}, {{ $i }})" class="text-gray-400 hover:text-red-600" title="Quitar fila">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                @endif
                            </div>
                        @endforeach
                        @error('podium.'.$place) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="closeModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cancelar</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        <span wire:loading.remove wire:target="savePodium">Guardar podio</span>
                        <span wire:loading wire:target="savePodium"><i class="fa-solid fa-spinner fa-spin"></i> Guardando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
