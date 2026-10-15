<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Participantes</h1>
            <p class="text-sm text-gray-500">Docentes y administrativos que compiten. Se agregan a un plantel desde «Planteles».</p>
        </div>

        @can('create', \App\Models\Player::class)
            <button wire:click="create" type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-plus"></i> Nuevo participante
            </button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar por nombre o DNI..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="statusFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all">Todos los estados</option>
            <option value="active">Activos</option>
            <option value="inactive">Inactivos</option>
        </select>

        <select wire:model.live="resolutionFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Con y sin resolución</option>
            <option value="with">Con resolución (PDF)</option>
            <option value="without">Sin resolución</option>
        </select>

        <select wire:model.live="perPage"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="10">10 por página</option>
            <option value="15">15 por página</option>
            <option value="25">25 por página</option>
            <option value="50">50 por página</option>
        </select>
        <x-export-button />
    </div>

    {{-- Tabla --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <x-sortable-th field="last_name" label="Apellidos" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="first_name" label="Nombres" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="dni" label="DNI" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="birth_date" label="Nacimiento" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Género / Condición</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Documentos</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Resolución</th>
                        <x-sortable-th field="is_active" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($players as $player)
                        <tr wire:key="player-{{ $player->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img class="h-8 w-8 rounded-full border object-cover"
                                         src="{{ $player->photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($player->photo_path) : 'https://ui-avatars.com/api/?name='.urlencode($player->fullName()).'&background=6366f1&color=fff' }}"
                                         alt="{{ $player->fullName() }}">
                                    <span class="font-medium text-gray-900">{{ $player->last_name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $player->first_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $player->dni }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $player->birth_date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                <p>{{ $player->gender?->label() ?? '—' }}</p>
                                <p class="text-xs text-gray-400">{{ collect([$player->staff_type?->label(), $player->employment_type?->label()])->filter()->implode(' · ') }}</p>
                            </td>
                            <td class="px-4 py-3">
                                @can('verifyDocuments', $player)
                                    <button type="button" wire:click="toggleDocuments({{ $player->id }})"
                                            title="{{ $player->documents_verified_at ? 'Quitar verificación' : 'Marcar como verificados' }}"
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $player->documents_verified_at ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-amber-100 text-amber-700 hover:bg-amber-200' }}">
                                        <i class="fa-solid {{ $player->documents_verified_at ? 'fa-circle-check' : 'fa-hourglass-half' }}"></i>
                                        {{ $player->documents_verified_at ? 'Verificados' : 'Por verificar' }}
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $player->documents_verified_at ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $player->documents_verified_at ? 'Verificados' : 'Por verificar' }}
                                    </span>
                                @endcan
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    @if ($player->resolution_path)
                                        <a href="{{ route('players.resolution', $player) }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-700 hover:bg-sky-200"
                                           title="Subida el {{ $player->resolution_uploaded_at?->format('d/m/Y H:i') }}">
                                            <i class="fa-solid fa-file-pdf"></i> PDF
                                        </a>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                            <i class="fa-solid fa-triangle-exclamation"></i> Sin resolución
                                        </span>
                                    @endif
                                    <button type="button" wire:click="openResolution({{ $player->id }})" class="text-gray-400 hover:text-indigo-600" title="Resolución">
                                        <i class="fa-solid fa-paperclip"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                             {{ $player->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $player->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', $player)
                                        <button wire:click="edit({{ $player->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    @endcan
                                    @can('delete', $player)
                                        <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar"
                                                x-on:click="confirmDelete(@js($player->fullName()), () => $wire.delete({{ $player->id }}))">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-400">No hay participantes que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $players->links() }}
        </div>
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar participante' : 'Nuevo participante' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombres</label>
                        <input type="text" wire:model="form.first_name"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Apellidos</label>
                        <input type="text" wire:model="form.last_name"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">DNI</label>
                    <input type="text" wire:model="form.dni" maxlength="12"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('form.dni') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Fecha de nacimiento</label>
                    <input type="date" wire:model="form.birth_date"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('form.birth_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Género</label>
                        <select wire:model="form.gender"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona</option>
                            <option value="F">Dama</option>
                            <option value="M">Varón</option>
                        </select>
                        @error('form.gender') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo</label>
                        <select wire:model="form.staff_type"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">—</option>
                            @foreach (\App\Enums\StaffType::cases() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('form.staff_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Condición</label>
                        <select wire:model="form.employment_type"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">—</option>
                            @foreach (\App\Enums\EmploymentType::cases() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('form.employment_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Fotografía</label>
                    <div class="mt-2 flex items-center gap-4">
                        @if ($newPhoto)
                            <img src="{{ $newPhoto->temporaryUrl() }}" class="h-14 w-14 rounded-full border object-cover">
                        @elseif ($existingPhotoUrl)
                            <img src="{{ $existingPhotoUrl }}" class="h-14 w-14 rounded-full border object-cover">
                        @else
                            <div class="flex h-14 w-14 items-center justify-center rounded-full border-2 border-dashed border-gray-300 text-gray-400">
                                <i class="fa-solid fa-camera"></i>
                            </div>
                        @endif
                        <input type="file" wire:model="newPhoto" accept="image/*"
                               class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <div wire:loading wire:target="newPhoto" class="mt-1 text-xs text-indigo-500">Cargando imagen...</div>
                    @error('newPhoto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-gray-400">JPG, PNG o GIF. Máx 2MB.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Resolución de nombramiento o contrato <span class="font-normal text-gray-400">(PDF, opcional)</span></label>
                    <input type="file" wire:model="newResolution" accept="application/pdf"
                           class="mt-2 block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    <div wire:loading wire:target="newResolution" class="mt-1 text-xs text-indigo-500">Cargando PDF...</div>
                    @error('newResolution') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-gray-400">Solo PDF, máx 5MB. Sirve para verificar la condición y atender reclamos. Si ya tiene una, se reemplaza.</p>
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
                        <span wire:loading.remove wire:target="save,newPhoto,newResolution">Guardar</span>
                        <span wire:loading wire:target="save,newPhoto,newResolution"><i class="fa-solid fa-spinner fa-spin"></i> Guardando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal resolucion (PDF): lo usan el delegado y el organizador --}}
    <div x-data="{ show: @entangle('showResolution') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeResolution()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            @if ($resolutionPlayer = $this->resolutionPlayer())
                <h2 class="text-lg font-semibold text-gray-900">Resolución</h2>
                <p class="text-sm text-gray-500">{{ $resolutionPlayer->fullName() }} · DNI {{ $resolutionPlayer->dni }}</p>

                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm">
                    @if ($resolutionPlayer->resolution_path)
                        <a href="{{ route('players.resolution', $resolutionPlayer) }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 font-medium text-indigo-600 hover:text-indigo-500">
                            <i class="fa-solid fa-file-pdf"></i> Ver resolución
                        </a>
                        <p class="mt-1 text-xs text-gray-500">
                            Subida el {{ $resolutionPlayer->resolution_uploaded_at?->format('d/m/Y H:i') }}
                            por {{ $resolutionPlayer->resolutionUploader?->name ?? '—' }}
                        </p>
                    @else
                        <p class="text-amber-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Aún no tiene resolución.</p>
                    @endif
                </div>

                @can('manageResolution', $resolutionPlayer)
                    <form wire:submit="saveResolution" class="mt-4 space-y-3">
                        <input type="file" wire:model="resolutionFile" accept="application/pdf"
                               class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <div wire:loading wire:target="resolutionFile" class="text-xs text-indigo-500">Cargando PDF...</div>
                        @error('resolutionFile') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="text-xs text-gray-400">Solo PDF, máx 5MB.</p>

                        <div class="flex items-center justify-between gap-3 pt-2">
                            @if ($resolutionPlayer->resolution_path)
                                <button type="button" class="text-sm font-medium text-red-600 hover:text-red-500"
                                        x-on:click="confirmDelete('la resolución', () => $wire.removeResolution())">
                                    Quitar resolución
                                </button>
                            @else
                                <span></span>
                            @endif
                            <div class="flex gap-3">
                                <button type="button" wire:click="closeResolution" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cancelar</button>
                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                                    {{ $resolutionPlayer->resolution_path ? 'Reemplazar' : 'Subir' }}
                                </button>
                            </div>
                        </div>
                    </form>
                @else
                    <p class="mt-4 text-xs text-gray-500">
                        @if ($resolutionPlayer->documents_verified_at)
                            Los documentos ya están verificados: solo el organizador puede reemplazar la resolución.
                        @else
                            No puedes cambiar la resolución de este participante.
                        @endif
                    </p>
                    <div class="mt-4 flex justify-end">
                        <button type="button" wire:click="closeResolution" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cerrar</button>
                    </div>
                @endcan
            @endif
        </div>
    </div>
</div>
