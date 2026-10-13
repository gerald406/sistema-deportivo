<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Series</h1>
            <p class="text-sm text-gray-500">Grupos de la olimpiada (A, B, C, D…). Las delegaciones se asignan a su serie al inscribirlas.</p>
        </div>

        @if ($edition)
            <button wire:click="create" type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-plus"></i> Nueva serie
            </button>
        @endif
    </div>

    <x-edition-picker :options="$this->editionOptions()" />

    @if (! $edition)
        <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
            No tienes ninguna olimpiada a tu cargo.
        </div>
    @else
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <x-sortable-th field="sort_order" label="Orden" :sort-field="$sortField" :sort-direction="$sortDirection" />
                            <x-sortable-th field="name" label="Serie" :sort-field="$sortField" :sort-direction="$sortDirection" />
                            <x-sortable-th field="edition_teams_count" label="Delegaciones" :sort-field="$sortField" :sort-direction="$sortDirection" />
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($groups as $group)
                            <tr wire:key="group-{{ $group->id }}">
                                <td class="px-4 py-3 text-gray-600">{{ $group->sort_order }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $group->name }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    <span class="font-medium text-gray-900">{{ $group->edition_teams_count }}</span>
                                    @if ($group->editionTeams->isNotEmpty())
                                        <span class="text-xs text-gray-400">· {{ $group->editionTeams->pluck('team.name')->sort()->implode(', ') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <button wire:click="edit({{ $group->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar"
                                                x-on:click="confirmDelete(@js($group->name), () => $wire.delete({{ $group->id }}))">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-gray-400">Esta olimpiada aún no tiene series.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar serie' : 'Nueva serie' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                    <input type="text" wire:model="form.name" placeholder="Ej: Serie A"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('form.name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Orden</label>
                    <input type="number" wire:model="form.sort_order" min="0" max="999"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('form.sort_order') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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
