<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Ediciones (Olimpiadas)</h1>
            <p class="text-sm text-gray-500">Agrupan temporadas de varios deportes en un mismo evento multideportivo con medallero.</p>
        </div>

        <button wire:click="create" type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
            <i class="fa-solid fa-plus"></i> Nueva edición
        </button>
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar por nombre..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

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
                        <x-sortable-th field="name" label="Nombre" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="start_date" label="Inicio" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="end_date" label="Fin" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Sede anfitriona</th>
                        <x-sortable-th field="seasons_count" label="Temporadas" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="is_active" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($editions as $edition)
                        <tr wire:key="edition-{{ $edition->id }}">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $edition->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $edition->start_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $edition->end_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $edition->hostVenue?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $edition->seasons_count }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                             {{ $edition->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $edition->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button wire:click="edit({{ $edition->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar"
                                            x-on:click="confirmDelete(@js($edition->name), () => $wire.delete({{ $edition->id }}))">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay ediciones que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $editions->links() }}
        </div>
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar edición' : 'Nueva edición' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                    <input type="text" wire:model="form.name" placeholder="Ej: Juegos Deportivos Nacionales 2027"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('form.name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
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
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Sede anfitriona <span class="text-gray-400">(opcional)</span></label>
                    <select wire:model="form.host_venue_id"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Sin sede anfitriona</option>
                        @foreach ($this->venues() as $venue)
                            <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                        @endforeach
                    </select>
                    @error('form.host_venue_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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
