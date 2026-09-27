<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Equipos</h1>
            <p class="text-sm text-gray-500">Catálogo global de equipos/delegaciones.</p>
        </div>

        @can('create', \App\Models\Team::class)
            <button wire:click="create" type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-plus"></i> Nuevo equipo
            </button>
        @endcan
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
            <option value="active">Activos</option>
            <option value="inactive">Inactivos</option>
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
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Delegado</th>
                        <x-sortable-th field="season_teams_count" label="Inscripciones" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="is_active" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="created_at" label="Creado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($teams as $team)
                        <tr wire:key="team-{{ $team->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img class="h-8 w-8 rounded-full border object-cover"
                                         src="{{ $team->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($team->logo_path) : 'https://ui-avatars.com/api/?name='.urlencode($team->name).'&background=6366f1&color=fff' }}"
                                         alt="{{ $team->name }}">
                                    <span class="font-medium text-gray-900">{{ $team->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $team->delegate?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $team->season_teams_count }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                             {{ $team->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $team->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $team->created_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', $team)
                                        <button wire:click="edit({{ $team->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    @endcan
                                    @can('delete', $team)
                                        <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar"
                                                x-on:click="confirmDelete(@js($team->name), () => $wire.delete({{ $team->id }}))">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">No hay equipos que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $teams->links() }}
        </div>
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar equipo' : 'Nuevo equipo' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                    <input type="text" wire:model="form.name"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('form.name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($this->isAdmin())
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium text-gray-700">Delegado responsable</label>
                            @if (! $form['delegate_id'])
                                <button type="button" wire:click="toggleCreatingDelegate"
                                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">
                                    {{ $creatingDelegate ? '← Volver al selector' : '+ Crear nueva cuenta' }}
                                </button>
                            @endif
                        </div>

                        @if ($creatingDelegate)
                            {{-- Solo visible para admin: crear cuentas es una regla de negocio
                                 exclusiva de admin, revalidada tambien en el Service. --}}
                            <div class="mt-2 space-y-2 rounded-lg bg-white p-3 ring-1 ring-gray-200">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <input type="text" wire:model="newDelegateFirstName" placeholder="Nombres"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @error('newDelegateFirstName') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <input type="text" wire:model="newDelegateLastName" placeholder="Apellidos"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @error('newDelegateLastName') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <input type="email" wire:model="newDelegateEmail" placeholder="Correo electrónico"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('newDelegateEmail') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                                <input type="text" wire:model="newDelegatePassword" placeholder="Contraseña temporal (mín. 8)"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('newDelegatePassword') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                                <button type="button" wire:click="saveNewDelegateAccount"
                                        class="w-full rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">
                                    <span wire:loading.remove wire:target="saveNewDelegateAccount">Guardar cuenta</span>
                                    <span wire:loading wire:target="saveNewDelegateAccount">Creando...</span>
                                </button>
                            </div>
                        @else
                            <select wire:model="form.delegate_id"
                                    class="mt-2 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Sin asignar</option>
                                @foreach ($this->delegates() as $delegate)
                                    <option value="{{ $delegate->id }}">{{ $delegate->name }}</option>
                                @endforeach
                            </select>
                            @error('form.delegate_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @endif
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700">Escudo / logo</label>
                    <div class="mt-2 flex items-center gap-4">
                        @if ($newLogo)
                            <img src="{{ $newLogo->temporaryUrl() }}" class="h-14 w-14 rounded-full border object-cover">
                        @elseif ($existingLogoUrl)
                            <img src="{{ $existingLogoUrl }}" class="h-14 w-14 rounded-full border object-cover">
                        @else
                            <div class="flex h-14 w-14 items-center justify-center rounded-full border-2 border-dashed border-gray-300 text-gray-400">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                        @endif
                        <input type="file" wire:model="newLogo" accept="image/*"
                               class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <div wire:loading wire:target="newLogo" class="mt-1 text-xs text-indigo-500">Cargando imagen...</div>
                    @error('newLogo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-gray-400">JPG, PNG o GIF. Máx 2MB.</p>
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="form.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Activo</span>
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="closeModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                        Cancelar
                    </button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        <span wire:loading.remove wire:target="save,newLogo">Guardar</span>
                        <span wire:loading wire:target="save,newLogo"><i class="fa-solid fa-spinner fa-spin"></i> Guardando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
