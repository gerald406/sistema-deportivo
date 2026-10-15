<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Fases y llaves</h1>
            <p class="text-sm text-gray-500">Cómo se juega cada competencia: series y fase final con partidos, o podio directo (solo 1.º, 2.º y 3.º).</p>
        </div>

        @php
            // Mismo boton arriba y en el listado vacio: una sola confirmacion.
            $confirmAll = "Swal.fire({
                title: 'Generar fases de todas las competencias',
                text: 'Se crearán las fases estándar de cada competencia activa de esta olimpiada que aún no tenga fases. Las que ya tienen fases no se modifican.',
                icon: 'question', showCancelButton: true,
                confirmButtonText: 'Generar', cancelButtonText: 'Cancelar', confirmButtonColor: '#059669',
            }).then((r) => { if (r.isConfirmed) \$wire.createStandardForAll() })";
        @endphp

        @if ($edition)
            <div class="flex flex-wrap gap-2">
                <button type="button" x-on:click="{{ $confirmAll }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-600">
                    <i class="fa-solid fa-layer-group"></i> Generar fases de todas
                </button>
                @if ($season)
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
                @endif
            </div>
        @endif
    </div>

    <x-edition-picker :options="$this->editionOptions()" />

    @if (! $edition)
        <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
            No tienes ninguna olimpiada a tu cargo.
        </div>
    @else

    {{-- Selector de competencia: vacio = listado de todas las fases --}}
    <div class="flex flex-col gap-2 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:flex-row sm:items-center">
        <label for="stage-season" class="text-sm font-medium text-gray-700">
            <i class="fa-solid fa-medal mr-1 text-amber-500"></i> Competencia
        </label>
        <select id="stage-season" wire:model.live="seasonId"
                class="flex-1 rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todas las competencias (listado de fases)</option>
            @foreach ($this->seasonOptions() as $option)
                <option value="{{ $option->id }}">{{ $option->name }}</option>
            @endforeach
        </select>
        @if ($season)
            <button type="button" wire:click="backToList" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50">
                <i class="fa-solid fa-arrow-left"></i> Volver al listado
            </button>
            <x-export-button />
        @endif
    </div>

    @if (! $season)
        {{-- Filtros del listado --}}
        <div class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative sm:col-span-2">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar competencia o fase..."
                       class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <select wire:model.live="sportFilter" class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos los deportes</option>
                @foreach ($filterSports as $sport)
                    <option value="{{ $sport->id }}">{{ $sport->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="categoryFilter" class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos los niveles</option>
                @foreach ($filterCategories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="genderFilter" class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Damas y varones</option>
                @foreach ($this->genderOptions() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="modeFilter" class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos los modos</option>
                @foreach ($this->modeOptions() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="groupFilter" class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todas las series</option>
                @foreach ($this->editionGroupOptions() as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
                <option value="none">Sin serie (finales)</option>
            </select>
            <select wire:model.live="progressFilter" class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Cualquier avance</option>
                @foreach ($this->progressOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="flex gap-3 sm:col-span-2 lg:col-span-1">
                <select wire:model.live="perPage" class="flex-1 rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="10">10 por página</option>
                    <option value="15">15 por página</option>
                    <option value="25">25 por página</option>
                    <option value="50">50 por página</option>
                </select>
                <x-export-button />
            </div>
        </div>

        {{-- Listado de fases --}}
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <x-sortable-th field="season_name" label="Competencia" :sort-field="$sortField" :sort-direction="$sortDirection" />
                            <x-sortable-th field="name" label="Fase" :sort-field="$sortField" :sort-direction="$sortDirection" />
                            <x-sortable-th field="mode" label="Modo" :sort-field="$sortField" :sort-direction="$sortDirection" />
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Serie</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Clasif.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">3.er puesto</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Avance</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Podio</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($allStages as $row)
                            <tr wire:key="row-{{ $row->id }}">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ $row->season->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $row->season->sport->name }}{{ $row->season->discipline ? ' — '.$row->season->discipline->name : '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $row->name }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $row->mode === \App\Enums\StageMode::Matches ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $row->mode->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $row->group?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $row->qualifiers ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $row->has_third_place ? 'Sí' : '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $this->progressLabel($row) }}</td>
                                <td class="px-4 py-3 text-xs text-gray-600">
                                    @forelse ($row->results as $result)
                                        <span class="block">{{ $result->place }}.º {{ $result->team?->name }}</span>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" wire:click="openSeason({{ $row->season_id }})"
                                            class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Abrir</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-gray-400">
                                    <p>No hay fases que coincidan con el filtro.</p>
                                    <button type="button" x-on:click="{{ $confirmAll }}"
                                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600">
                                        <i class="fa-solid fa-layer-group"></i> Generar fases de todas las competencias
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-4 py-3">
                {{ $allStages->links() }}
            </div>
        </div>

        {{-- Lo que falta: competencias activas sin fases --}}
        @php($missing = $this->seasonsWithoutStages())
        @if ($missing->isNotEmpty())
            <div class="rounded-xl bg-amber-50 p-4 ring-1 ring-amber-200">
                <p class="text-sm font-semibold text-amber-800">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                    {{ $missing->count() }} {{ $missing->count() === 1 ? 'competencia activa no tiene' : 'competencias activas no tienen' }} fases
                </p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($missing as $item)
                        <button type="button" wire:key="missing-{{ $item->id }}" wire:click="openSeason({{ $item->id }})"
                                class="rounded-full bg-white px-3 py-1 text-xs font-medium text-amber-800 ring-1 ring-amber-200 hover:bg-amber-100">
                            {{ $item->name }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
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
                            @if ($stage->mode === \App\Enums\StageMode::Matches)
                                @if ($stage->matches->isEmpty())
                                    <button wire:click="openBracket({{ $stage->id }})" type="button"
                                            class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-500">
                                        <i class="fa-solid fa-sitemap"></i> Generar llave
                                    </button>
                                @else
                                    <button type="button" class="text-sm font-medium text-gray-500 hover:text-red-600"
                                            x-on:click="confirmDelete(@js('la llave de '.$stage->name), () => $wire.clearBracket({{ $stage->id }}))">
                                        <i class="fa-solid fa-eraser"></i> Borrar llave
                                    </button>
                                @endif
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

                    {{-- Llave por rondas --}}
                    @if ($stage->mode === \App\Enums\StageMode::Matches && $stage->matches->isNotEmpty())
                        @php($thirdPlace = \App\Enums\RoundType::ThirdPlace)
                        <div class="mt-4 flex gap-4 overflow-x-auto pb-2">
                            @foreach ($stage->matches->where('round_type', '!=', $thirdPlace)->groupBy('bracket_round') as $roundMatches)
                                <div class="min-w-[14rem] flex-1 space-y-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $roundMatches->first()->round_type->label() }}</p>
                                    @foreach ($roundMatches as $match)
                                        @include('livewire.season-stages.partials.bracket-match', ['match' => $match])
                                    @endforeach
                                </div>
                            @endforeach
                            @if ($third = $stage->matches->firstWhere('round_type', $thirdPlace))
                                <div class="min-w-[14rem] flex-1 space-y-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $thirdPlace->label() }}</p>
                                    @include('livewire.season-stages.partials.bracket-match', ['match' => $third])
                                </div>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Fecha, hora y sede se ponen en Partidos; el marcador, en Resultados. El ganador pasa solo a la ronda siguiente.</p>
                    @endif

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

    {{-- Modal generar llave --}}
    <div x-data="{ show: @entangle('showBracket') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">Generar llave</h2>
            <p class="text-sm text-gray-500">
                El orden decide los cruces: el 1.º juega con el último, el 2.º con el penúltimo.
                Si el número no es 2, 4, 8 o 16, los primeros de la lista descansan en la primera ronda.
            </p>

            <form wire:submit="generateBracket" class="mt-4 space-y-4">
                @error('bracket') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror

                <ol class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                    @forelse ($seeds as $i => $seedId)
                        <li wire:key="seed-{{ $seedId }}" class="flex items-center gap-3 px-3 py-2 text-sm">
                            <span class="w-6 text-right font-semibold text-gray-400">{{ $i + 1 }}</span>
                            <span class="flex-1 font-medium text-gray-900">{{ $entrantNames[$seedId] ?? '¿?' }}</span>
                            <button type="button" wire:click="moveSeed({{ $i }}, -1)" @disabled($i === 0) class="text-gray-400 hover:text-indigo-600 disabled:opacity-30" title="Subir"><i class="fa-solid fa-arrow-up"></i></button>
                            <button type="button" wire:click="moveSeed({{ $i }}, 1)" @disabled($i === count($seeds) - 1) class="text-gray-400 hover:text-indigo-600 disabled:opacity-30" title="Bajar"><i class="fa-solid fa-arrow-down"></i></button>
                            <button type="button" wire:click="removeSeed({{ $i }})" class="text-gray-400 hover:text-red-600" title="Quitar"><i class="fa-solid fa-xmark"></i></button>
                        </li>
                    @empty
                        <li class="px-3 py-6 text-center text-sm text-gray-400">
                            Sin participantes. Solo aparecen las delegaciones que ya tienen plantel en esta competencia.
                        </li>
                    @endforelse
                </ol>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <select wire:model="seedToAdd" class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Agregar delegación…</option>
                        @foreach ($showBracket ? $this->entrantOptions() : [] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="addSeed" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Agregar</button>
                    <button type="button" wire:click="shuffleSeeds" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fa-solid fa-shuffle"></i> Sortear
                    </button>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="closeModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cancelar</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        <span wire:loading.remove wire:target="generateBracket">Generar llave</span>
                        <span wire:loading wire:target="generateBracket"><i class="fa-solid fa-spinner fa-spin"></i> Generando...</span>
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
