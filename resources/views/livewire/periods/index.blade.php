<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Parciales</h1>
        <p class="text-sm text-gray-500">Sets, tiempos o cuartos de cada enfrentamiento (local vs. visitante).</p>
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar equipo o jugador..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="seasonFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todas las temporadas</option>
            @foreach ($filterSeasons as $season)
                <option value="{{ $season->id }}">{{ $season->tournament->name }} — {{ $season->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="matchStatusFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all">Todos los estados</option>
            @foreach ($statusOptions as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
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
                        <x-sortable-th field="scheduled_at" label="Partido" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="periods_count" label="Parciales" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Total</th>
                        <x-sortable-th field="status" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($matches as $match)
                        <tr wire:key="periods-{{ $match->id }}">
                            <td class="px-4 py-3">
                                @if ($match->participants->count() === 2)
                                    <p class="font-medium text-gray-900">
                                        {{ $this->label($match->participants[0]) }}
                                        <span class="px-1 text-xs font-normal text-gray-400">vs</span>
                                        {{ $this->label($match->participants[1]) }}
                                    </p>
                                @endif
                                <p class="text-xs text-gray-400">
                                    {{ $match->season->name }} · {{ $match->matchday->name }} · {{ $match->scheduled_at?->format('d/m/Y H:i') ?? 'sin fecha' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                @forelse ($match->periods as $period)
                                    <span class="mr-2 whitespace-nowrap font-mono text-xs">{{ $period->home_points }}-{{ $period->away_points }}</span>
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                @if ($match->periods->isNotEmpty())
                                    {{ $match->periods->sum('home_points') }} - {{ $match->periods->sum('away_points') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $match->status->badgeClasses() }}">
                                    {{ $match->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($match->status === \App\Enums\MatchStatus::Pending)
                                    <div class="flex items-center justify-end gap-3">
                                        <button wire:click="edit({{ $match->id }})" class="text-gray-400 hover:text-indigo-600" title="Cargar parciales">
                                            <i class="fa-solid fa-table-cells"></i>
                                        </button>
                                        @if ($match->periods_count > 0)
                                            <button type="button" class="text-gray-400 hover:text-red-600" title="Borrar parciales"
                                                    x-on:click="confirmDelete(@js('los parciales del partido #'.$match->id), () => $wire.clear({{ $match->id }}))">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-400">No hay enfrentamientos que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $matches->links() }}
        </div>
    </div>

    {{-- Modal: parciales --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            @php($editing = $this->editingMatch())
            <h2 class="text-lg font-semibold text-gray-900">Parciales</h2>

            <form wire:submit="save" class="mt-4 space-y-3">
                @error('periods') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                @if ($editing && $editing->participants->count() === 2)
                    <div class="grid grid-cols-[4rem_1fr_1fr_1.5rem] items-center gap-2 text-xs font-semibold uppercase text-gray-500">
                        <span></span>
                        <span class="truncate">{{ $this->label($editing->participants[0]) }}</span>
                        <span class="truncate">{{ $this->label($editing->participants[1]) }}</span>
                        <span></span>
                    </div>
                @endif

                @foreach ($periods as $i => $period)
                    <div wire:key="period-{{ $i }}" class="grid grid-cols-[4rem_1fr_1fr_1.5rem] items-center gap-2">
                        <span class="text-sm font-medium text-gray-500">P{{ $i + 1 }}</span>
                        <input type="number" min="0" wire:model="periods.{{ $i }}.home"
                               class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <input type="number" min="0" wire:model="periods.{{ $i }}.away"
                               class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="button" wire:click="removePeriod({{ $i }})" class="text-gray-400 hover:text-red-600" title="Quitar">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                @endforeach

                <button type="button" wire:click="addPeriod" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">+ Agregar periodo</button>

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
