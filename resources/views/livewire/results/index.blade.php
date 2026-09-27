<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Resultados</h1>
        <p class="text-sm text-gray-500">Marcadores, walkovers y marcas de cada partido o prueba. Cerrar un resultado actualiza la tabla y el medallero.</p>
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
                        <x-sortable-th field="scheduled_at" label="Partido / prueba" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Resultado</th>
                        <x-sortable-th field="status" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($matches as $match)
                        @php($format = $this->formatOf($match))
                        @php($isH2H = $format === \App\Enums\SportFormatType::HeadToHead)
                        <tr wire:key="result-{{ $match->id }}">
                            <td class="px-4 py-3">
                                @if ($isH2H && $match->participants->count() === 2)
                                    <p class="font-medium text-gray-900">
                                        {{ $this->label($match->participants[0]) }}
                                        <span class="px-1 text-xs font-normal text-gray-400">vs</span>
                                        {{ $this->label($match->participants[1]) }}
                                    </p>
                                @else
                                    <p class="font-medium text-gray-900">{{ $match->discipline?->name ?? $match->season->sport->name }}
                                        <span class="text-xs font-normal text-gray-400">({{ $match->participants_count }})</span></p>
                                @endif
                                <p class="text-xs text-gray-400">
                                    {{ $match->season->name }} · {{ $match->matchday->name }} · {{ $match->scheduled_at?->format('d/m/Y H:i') ?? 'sin fecha' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                @if ($match->status === \App\Enums\MatchStatus::Walkover)
                                    @php($absent = $match->participants->firstWhere('position', 2))
                                    <span class="text-amber-700">W.O. — no se presentó {{ $absent ? $this->label($absent) : '?' }}</span>
                                @elseif ($match->status === \App\Enums\MatchStatus::Played && $isH2H)
                                    <span class="font-mono text-base font-semibold">{{ (int) $match->participants[0]->result_value }} - {{ (int) $match->participants[1]->result_value }}</span>
                                @elseif ($match->status === \App\Enums\MatchStatus::Played)
                                    @foreach ($match->participants->whereNotNull('position')->sortBy('position')->take(3) as $p)
                                        <span class="mr-2 whitespace-nowrap text-xs"><span class="font-semibold">{{ $p->position }}°</span> {{ $this->label($p) }} ({{ rtrim(rtrim((string) $p->result_value, '0'), '.') }})</span>
                                    @endforeach
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $match->status->badgeClasses() }}">
                                    {{ $match->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @if ($match->status === \App\Enums\MatchStatus::Pending && $match->participants_count > 0)
                                        <button wire:click="openResult({{ $match->id }})" class="text-gray-400 hover:text-indigo-600" title="Cargar resultado">
                                            <i class="fa-solid fa-flag-checkered"></i>
                                        </button>
                                    @endif
                                    @if ($this->canReopen($match))
                                        <button wire:click="openReopen({{ $match->id }})" class="text-gray-400 hover:text-amber-600" title="Reabrir partido">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-gray-400">No hay partidos que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $matches->links() }}
        </div>
    </div>

    {{-- Modal: cargar resultado --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
            @php($editing = $this->editingMatch())
            <h2 class="text-lg font-semibold text-gray-900">Cargar resultado</h2>

            @if ($editing)
                @php($format = $this->formatOf($editing))
                <p class="text-sm text-gray-500">{{ $editing->discipline?->name ?? $editing->season->sport->name }} · {{ $editing->matchday->name }} · {{ $format->label() }}</p>

                <form wire:submit="saveResult" class="mt-4 space-y-4">
                    @error('result') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror

                    @if ($format === \App\Enums\SportFormatType::HeadToHead && $editing->participants->count() === 2)
                        @php([$home, $away] = [$editing->participants[0], $editing->participants[1]])
                        <div>
                            <label class="block text-sm font-medium text-gray-700">¿Se jugó?</label>
                            <select wire:model.live="walkover"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Sí, se jugó</option>
                                <option value="home">Walkover: no se presentó {{ $this->label($home) }}</option>
                                <option value="away">Walkover: no se presentó {{ $this->label($away) }}</option>
                            </select>
                        </div>

                        @if ($walkover === '')
                            @php($derived = $this->derivedScore())
                            @if ($derived)
                                <div class="rounded-lg bg-indigo-50 p-3 text-sm text-indigo-900">
                                    Marcador según parciales{{ app(\App\Services\ResultService::class)->isBestOf($editing) ? ' (sets ganados)' : '' }}:
                                    <span class="font-mono text-base font-semibold">{{ $derived['home'] }} - {{ $derived['away'] }}</span>
                                    <p class="text-xs text-indigo-600">Para cambiarlo, corrige los parciales.</p>
                                </div>
                            @else
                                <div class="grid grid-cols-2 gap-4">
                                    @foreach (['homeScore' => $home, 'awayScore' => $away] as $field => $p)
                                        <div>
                                            <label class="block truncate text-sm font-medium text-gray-700">{{ $this->label($p) }}</label>
                                            <input type="number" min="0" wire:model="{{ $field }}"
                                                   class="mt-1 w-full rounded-lg border-gray-300 text-lg focus:border-indigo-500 focus:ring-indigo-500">
                                            @error('result.'.($field === 'homeScore' ? 'home' : 'away')) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    @else
                        @php($unit = $editing->discipline?->unit_of_measure?->value)
                        <p class="text-xs text-gray-500">
                            Marca en {{ $unit ?? 'puntos' }};
                            {{ ($editing->discipline?->better_direction?->value ?? 'desc') === 'asc' ? 'menor es mejor' : 'mayor es mejor' }}.
                            Deja vacío a quien no clasificó. El puesto se calcula solo.
                        </p>
                        <div class="space-y-2">
                            @foreach ($editing->participants as $p)
                                <div wire:key="mark-{{ $p->id }}" class="flex items-center gap-3">
                                    <span class="w-12 text-xs text-gray-400">{{ $p->lane_or_board ? 'C'.$p->lane_or_board : '' }}</span>
                                    <span class="flex-1 truncate text-sm text-gray-800">{{ $this->label($p) }}</span>
                                    <input type="number" step="0.0001" min="0" wire:model="marks.{{ $p->id }}"
                                           class="w-32 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                @error('results.'.$p->id) <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            @endforeach
                        </div>
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                            <span wire:loading.remove wire:target="saveResult">Cerrar resultado</span>
                            <span wire:loading wire:target="saveResult"><i class="fa-solid fa-spinner fa-spin"></i> Guardando...</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- Modal: reabrir partido --}}
    <div x-data="{ show: @entangle('showReopenModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">Reabrir partido</h2>
            <p class="mt-1 text-sm text-gray-500">
                El partido vuelve a "Programado", se borran marcas y puestos, y las sanciones no cumplidas que originó.
                Queda registrado quién lo reabrió y por qué.
            </p>

            <form wire:submit="confirmReopen" class="mt-4 space-y-3">
                <textarea wire:model="reopenReason" rows="3" placeholder="Motivo (ej: se cargó mal el marcador)"
                          class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                @error('reason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="closeModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cancelar</button>
                    <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-500">Reabrir</button>
                </div>
            </form>
        </div>
    </div>
</div>
