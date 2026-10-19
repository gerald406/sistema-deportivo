<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Cuadro de puntaje</h1>
            <p class="text-sm text-gray-500">
                Puntos que cada podio suma al puntaje general y topes de plantel por deporte y nivel.
                Un campo vacío significa sin puntos o sin restricción.
            </p>
        </div>

        @if ($edition)
            <div class="flex flex-wrap gap-2">
                <x-export-button />
                @if ($canEdit)
                    <button wire:click="create" type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        <i class="fa-solid fa-plus"></i> Nueva fila
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
        @if ($canEdit)
            <p class="rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-800">
                <i class="fa-solid fa-circle-info mr-1"></i>
                Guardar una fila no crea competencias. Después de agregar o cambiar filas, pulsa
                <strong>Generar competencias</strong> en
                <a href="{{ route('seasons.index', ['olimpiada' => $edition->id]) }}" wire:navigate class="font-medium underline hover:text-sky-600">Competencias</a>
                para crear las que falten.
            </p>
        @else
            <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <i class="fa-solid fa-lock mr-1"></i> Solo el administrador modifica este cuadro.
            </p>
        @endif

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">N.º</th>
                            <th class="px-4 py-3">Etiqueta</th>
                            <th class="px-4 py-3">Deporte</th>
                            <th class="px-4 py-3">Nivel</th>
                            <th class="px-4 py-3">Alcance</th>
                            <th class="px-4 py-3 text-right">1.º</th>
                            <th class="px-4 py-3 text-right">2.º</th>
                            <th class="px-4 py-3 text-right">3.º</th>
                            <th class="px-4 py-3 text-right">Mín.</th>
                            <th class="px-4 py-3 text-right">Máx.</th>
                            <th class="px-4 py-3">Bloqueante</th>
                            @if ($canEdit)
                                <th class="px-4 py-3"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($events as $event)
                            <tr wire:key="event-{{ $event->id }}" @class(['opacity-50' => ! $event->is_active])>
                                <td class="px-4 py-3 text-gray-600">{{ $event->sort_order }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    {{ $event->label }}
                                    @unless ($event->is_active)
                                        <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-normal text-gray-600">Inactiva</span>
                                    @endunless
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $event->sport?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $event->category?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $event->gender_scope->label() }}
                                    @if ($event->gender_scope === \App\Enums\GenderScope::Both)
                                        <span class="block text-xs text-gray-400">{{ $event->separate_podiums ? 'Un podio por género' : 'Podio combinado' }}</span>
                                    @endif
                                    @if ($event->per_discipline)
                                        <span class="block text-xs text-gray-400">Una competencia por prueba</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900">{{ $event->pts_first ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ $event->pts_second ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ $event->pts_third ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ $event->min_players ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ $event->max_players ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $event->rules_blocking ? 'Sí' : 'Solo advierte' }}</td>
                                @if ($canEdit)
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <button wire:click="edit({{ $event->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button type="button" class="text-gray-400 hover:text-red-600" title="Eliminar"
                                                    x-on:click="confirmDelete(@js($event->label), () => $wire.delete({{ $event->id }}))">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canEdit ? 12 : 11 }}" class="px-4 py-10 text-center text-gray-400">
                                    Esta olimpiada aún no tiene cuadro de puntaje.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($events->isNotEmpty())
                        @php($active = $events->where('is_active', true))
                        <tfoot class="bg-gray-50 text-sm font-semibold text-gray-900">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right">Suma de las filas activas</td>
                                <td class="px-4 py-3 text-right">{{ $active->sum('pts_first') }}</td>
                                <td class="px-4 py-3 text-right">{{ $active->sum('pts_second') }}</td>
                                <td class="px-4 py-3 text-right">{{ $active->sum('pts_third') }}</td>
                                <td colspan="{{ $canEdit ? 4 : 3 }}"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif

    {{-- Modal crear/editar (solo admin; save() lo revalida) --}}
    @if ($canEdit)
        <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

            <div x-show="show" x-transition class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ $editingId ? 'Editar fila del cuadro' : 'Nueva fila del cuadro' }}
                </h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-3">
                            <label class="block text-sm font-medium text-gray-700">Etiqueta</label>
                            <input type="text" wire:model="form.label" placeholder="Ej: Futsal Máster"
                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('form.label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">N.º</label>
                            <input type="number" wire:model="form.sort_order" min="0" max="999"
                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('form.sort_order') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Deporte o actividad</label>
                            <select wire:model="form.sport_id"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Ninguno —</option>
                                @foreach ($sports as $sport)
                                    <option value="{{ $sport->id }}">{{ $sport->name }}</option>
                                @endforeach
                            </select>
                            @error('form.sport_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nivel</label>
                            <select wire:model="form.category_id"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Sin nivel —</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('form.category_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Alcance</label>
                            <select wire:model="form.gender_scope"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($scopes as $scope)
                                    <option value="{{ $scope->value }}">{{ $scope->label() }}</option>
                                @endforeach
                            </select>
                            @error('form.gender_scope') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="form.separate_podiums" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>Podios separados por género
                                <span class="block text-xs text-gray-400">Con alcance "Damas y varones": un podio para damas y otro para varones, cada uno con estos puntos.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="form.per_discipline" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>Una competencia por prueba
                                <span class="block text-xs text-gray-400">Cada prueba del deporte (100 m, salto largo…) tiene su podio con estos puntos.</span>
                            </span>
                        </label>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-700">Puntos para el puntaje general</p>
                        <div class="mt-1 grid grid-cols-3 gap-4">
                            @foreach (['pts_first' => '1.º puesto', 'pts_second' => '2.º puesto', 'pts_third' => '3.º puesto'] as $field => $label)
                                <div>
                                    <label class="block text-xs text-gray-500">{{ $label }}</label>
                                    <input type="number" wire:model="form.{{ $field }}" min="0" max="999" placeholder="Sin puntos"
                                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('form.'.$field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-700">Participantes por delegación</p>
                        <div class="mt-1 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500">Mínimo</label>
                                <input type="number" wire:model="form.min_players" min="0" max="999" placeholder="Sin mínimo"
                                       class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('form.min_players') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Máximo</label>
                                <input type="number" wire:model="form.max_players" min="1" max="999" placeholder="Sin máximo"
                                       class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('form.max_players') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="form.rules_blocking" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>Reglas bloqueantes
                                <span class="block text-xs text-gray-400">Si no se marca, edad, género y topes solo advierten al inscribir.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="form.is_active" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>Fila activa</span>
                        </label>
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
    @endif
</div>
