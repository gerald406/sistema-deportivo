<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Deportes y disciplinas</h1>
            <p class="text-sm text-gray-500">Catálogo base del que dependen temporadas y partidos.</p>
        </div>
        <button wire:click="createSport" type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
            <i class="fa-solid fa-plus"></i> Nuevo deporte
        </button>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5">
        <div class="relative max-w-sm">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar deporte..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <x-sortable-th field="name" label="Nombre" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Formato</th>
                        <x-sortable-th field="disciplines_count" label="Disciplinas" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="is_active" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($sports as $sport)
                        <tr wire:key="sport-{{ $sport->id }}">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $sport->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $sport->format_type->label() }}</td>
                            <td class="px-4 py-3">
                                <button wire:click="manageDisciplines({{ $sport->id }})" class="text-indigo-600 hover:underline">
                                    {{ $sport->disciplines_count }} {{ Str::plural('disciplina', $sport->disciplines_count) }}
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                             {{ $sport->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $sport->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button wire:click="editSport({{ $sport->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar"
                                            x-on:click="confirmDelete(@js($sport->name), () => $wire.deleteSport({{ $sport->id }}))">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No hay deportes registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-4 py-3">{{ $sports->links() }}</div>
    </div>

    {{-- Modal Sport --}}
    <div x-data="{ show: @entangle('showSportModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeSportModal()" class="fixed inset-0 bg-gray-900/50"></div>
        <div x-show="show" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">{{ $editingSportId ? 'Editar deporte' : 'Nuevo deporte' }}</h2>
            <form wire:submit="saveSport" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                    <input type="text" wire:model="sportForm.name" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('sportForm.name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Slug (opcional, se genera del nombre)</label>
                    <input type="text" wire:model="sportForm.slug" placeholder="ej: futbol" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('sportForm.slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Formato de competencia</label>
                    <select wire:model="sportForm.format_type" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($this->formatTypeOptions() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="sportForm.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Activo</span>
                </label>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="closeSportModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cancelar</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Disciplinas (anidado por deporte) --}}
    <div x-data="{ show: @entangle('showDisciplinesModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeDisciplinesModal()" class="fixed inset-0 bg-gray-900/50"></div>
        <div x-show="show" x-transition class="relative w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">
                    Disciplinas de {{ $this->managingSport()?->name }}
                </h2>
                <button wire:click="closeDisciplinesModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Formulario --}}
                <form wire:submit="saveDiscipline" class="space-y-3 rounded-lg border border-gray-200 p-4">
                    <p class="text-sm font-semibold text-gray-700">
                        {{ $editingDisciplineId ? 'Editar disciplina' : 'Nueva disciplina' }}
                    </p>
                    <div>
                        <input type="text" wire:model="disciplineForm.name" placeholder="Nombre (ej: 100 metros llanos)"
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('disciplineForm.name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Formato (opcional, sobrescribe al del deporte)</label>
                        <select wire:model="disciplineForm.format_type" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Usar el del deporte —</option>
                            @foreach ($this->formatTypeOptions() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500">Unidad de medida</label>
                            <select wire:model="disciplineForm.unit_of_measure" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($this->unitOfMeasureOptions() as $option)
                                    <option value="{{ $option->value }}">{{ $option->value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500">Mejor resultado</label>
                            <select wire:model="disciplineForm.better_direction" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($this->betterDirectionOptions() as $option)
                                    <option value="{{ $option->value }}">{{ $option === App\Enums\BetterDirection::Ascending ? 'Menor es mejor (asc)' : 'Mayor es mejor (desc)' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="disciplineForm.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Activa</span>
                    </label>
                    <div class="flex justify-end gap-2 pt-1">
                        @if ($editingDisciplineId)
                            <button type="button" wire:click="createDiscipline" class="rounded-lg px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100">Cancelar edición</button>
                        @endif
                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Guardar</button>
                    </div>
                </form>

                {{-- Listado --}}
                <div class="max-h-80 space-y-2 overflow-y-auto">
                    @forelse ($disciplines as $discipline)
                        <div wire:key="discipline-{{ $discipline->id }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $discipline->name }}</p>
                                <p class="text-xs text-gray-500">{{ $discipline->unit_of_measure->value }} · {{ $discipline->better_direction->value }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                             {{ $discipline->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $discipline->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                                <button wire:click="editDiscipline({{ $discipline->id }})" class="text-gray-400 hover:text-indigo-600">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                                <button type="button" class="text-gray-400 hover:text-red-600"
                                        x-on:click="confirmDelete(@js($discipline->name), () => $wire.deleteDiscipline({{ $discipline->id }}))">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-sm text-gray-400">Este deporte no tiene disciplinas aún.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
