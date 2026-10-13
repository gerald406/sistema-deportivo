<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Delegaciones inscritas</h1>
            <p class="text-sm text-gray-500">Cada delegación tiene su serie y un delegado titular y un suplente, con los mismos permisos.</p>
        </div>

        @if ($edition)
            <button wire:click="create" type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-plus"></i> Inscribir delegación
            </button>
        @endif
    </div>

    <x-edition-picker :options="$this->editionOptions()" />

    @if (! $edition)
        <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
            No tienes ninguna olimpiada a tu cargo.
        </div>
    @else
        {{-- Filtros --}}
        <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Buscar delegación..."
                       class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <select wire:model.live="groupFilter"
                    class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todas las series</option>
                @foreach ($this->groupOptions() as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="statusFilter"
                    class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="all">Todos los estados</option>
                @foreach ($this->statusOptions() as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="perPage"
                    class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="10">10 por página</option>
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
                            <x-sortable-th field="team_name" label="Delegación" :sort-field="$sortField" :sort-direction="$sortDirection" />
                            <x-sortable-th field="group_order" label="Serie" :sort-field="$sortField" :sort-direction="$sortDirection" />
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Delegado titular</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Delegado suplente</th>
                            <x-sortable-th field="fee_paid" label="Pago" :sort-field="$sortField" :sort-direction="$sortDirection" />
                            <x-sortable-th field="status" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($entries as $entry)
                            <tr wire:key="edition-team-{{ $entry->id }}">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $entry->team->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $entry->group?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    @if ($entry->delegate)
                                        <p>{{ $entry->delegate->name }}</p>
                                        <p class="text-xs text-gray-400">{{ $entry->delegate->email }}</p>
                                    @else
                                        <span class="text-amber-600">Sin asignar</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    @if ($entry->substituteDelegate)
                                        <p>{{ $entry->substituteDelegate->name }}</p>
                                        <p class="text-xs text-gray-400">{{ $entry->substituteDelegate->email }}</p>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                                 {{ $entry->fee_paid ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $entry->fee_paid ? 'Registrado' : 'Pendiente' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $entry->status->badgeClasses() }}">
                                        {{ $entry->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <button wire:click="edit({{ $entry->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar inscripción"
                                                x-on:click="confirmDelete(@js($entry->team->name), () => $wire.delete({{ $entry->id }}))">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay delegaciones inscritas que coincidan con el filtro.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-4 py-3">
                {{ $entries->links() }}
            </div>
        </div>
    @endif

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar delegación inscrita' : 'Inscribir delegación' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Delegación</label>
                    @if ($editingId)
                        <p class="mt-1 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            {{ \App\Models\Team::find($form['team_id'])?->name }}
                        </p>
                    @else
                        <select wire:model="form.team_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona una delegación</option>
                            @foreach ($this->teamOptions() as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">¿No aparece? Créala primero en Delegaciones.</p>
                    @endif
                    @error('form.team_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Serie</label>
                        <select wire:model="form.group_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Sin serie</option>
                            @foreach ($this->groupOptions() as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                        @error('form.group_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estado</label>
                        <select wire:model="form.status"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($this->statusOptions() as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('form.status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-700">Delegados</p>
                        <button type="button" wire:click="toggleCreatingDelegate" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">
                            {{ $creatingDelegate ? '← Volver' : '+ Crear cuenta de delegado' }}
                        </button>
                    </div>

                    @if ($creatingDelegate)
                        <div class="mt-3 space-y-2">
                            <input type="text" wire:model="newDelegate.name" placeholder="Nombres y apellidos"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('newDelegate.name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <input type="email" wire:model="newDelegate.email" placeholder="Correo electrónico"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('newDelegate.email') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <input type="text" wire:model="newDelegate.password" placeholder="Contraseña temporal (mín. 8)"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('newDelegate.password') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <button type="button" wire:click="saveNewDelegateAccount"
                                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">
                                <span wire:loading.remove wire:target="saveNewDelegateAccount">Guardar cuenta</span>
                                <span wire:loading wire:target="saveNewDelegateAccount">Creando...</span>
                            </button>
                            <p class="text-xs text-gray-400">La cuenta se crea solo con el rol delegado.</p>
                        </div>
                    @else
                        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Titular</label>
                                <select wire:model="form.delegate_id"
                                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Sin asignar</option>
                                    @foreach ($this->delegateOptions() as $delegate)
                                        <option value="{{ $delegate->id }}">{{ $delegate->name }}</option>
                                    @endforeach
                                </select>
                                @error('form.delegate_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600">Suplente</label>
                                <select wire:model="form.substitute_delegate_id"
                                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Sin asignar</option>
                                    @foreach ($this->delegateOptions() as $delegate)
                                        <option value="{{ $delegate->id }}">{{ $delegate->name }}</option>
                                    @endforeach
                                </select>
                                @error('form.substitute_delegate_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="form.fee_paid" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Pago de inscripción registrado</span>
                </label>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Observaciones <span class="text-gray-400">(opcional)</span></label>
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
