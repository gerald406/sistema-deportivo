<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Relevos de postas</h1>
        <p class="text-sm text-gray-500">Orden de los relevistas de cada equipo en las pruebas de posta (ej. 4x100).</p>
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 lg:flex-row lg:items-center">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar equipo..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="matchStatusFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all">Todos los estados</option>
            @foreach ($statusOptions as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>

        <select wire:model.live="lineupFilter"
                class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all">Con y sin relevos</option>
            <option value="missing">Sin relevos cargados</option>
            <option value="loaded">Con relevos cargados</option>
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
                        <x-sortable-th field="match_scheduled_at" label="Posta" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="team_name" label="Equipo" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="lane_or_board" label="Carril" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Relevistas</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($participants as $p)
                        <tr wire:key="relay-{{ $p->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $p->match->discipline?->name ?? $p->match->season->sport->name }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ $p->match->season->name }} · {{ $p->match->matchday->name }}
                                    · {{ $p->match->scheduled_at?->format('d/m/Y H:i') ?? 'sin fecha' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $p->participant?->team->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $p->lane_or_board ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @forelse ($p->lineups as $leg)
                                    <span class="mr-2 whitespace-nowrap"><span class="font-semibold text-gray-400">{{ $leg->leg_order }}.</span> {{ $leg->seasonTeamPlayer->player->last_name }}</span>
                                @empty
                                    <span class="text-amber-600">Sin cargar</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $p->match->status->badgeClasses() }}">
                                    {{ $p->match->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($p->match->status === \App\Enums\MatchStatus::Pending && $this->canManage($p))
                                    <div class="flex items-center justify-end gap-3">
                                        <button wire:click="edit({{ $p->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar relevos">
                                            <i class="fa-solid fa-list-ol"></i>
                                        </button>
                                        @if ($p->lineups->isNotEmpty())
                                            <button type="button" class="text-gray-400 hover:text-red-600" title="Borrar orden de relevos"
                                                    x-on:click="confirmDelete(@js('relevos de '.$p->participant?->team->name), () => $wire.clear({{ $p->id }}))">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">No hay equipos en postas que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $participants->links() }}
        </div>
    </div>

    {{-- Modal: orden de relevistas --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            @php($editing = $this->editingParticipant())
            <h2 class="text-lg font-semibold text-gray-900">Orden de relevos</h2>
            @if ($editing)
                <p class="text-sm text-gray-500">{{ $editing->participant?->team->name }} — {{ $editing->match->discipline?->name }} ({{ $editing->match->matchday->name }})</p>
            @endif

            <form wire:submit="save" class="mt-4 space-y-3">
                @error('legs') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                @php($runners = $this->runnerOptions())
                @foreach ($legs as $i => $leg)
                    <div wire:key="leg-{{ $i }}" class="flex items-center gap-2">
                        <span class="w-16 text-sm font-medium text-gray-500">Tramo {{ $i + 1 }}</span>
                        <select wire:model="legs.{{ $i }}"
                                class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona relevista...</option>
                            @foreach ($runners as $runner)
                                <option value="{{ $runner->id }}">{{ $runner->player->last_name }}, {{ $runner->player->first_name }}{{ $runner->shirt_number ? ' (#'.$runner->shirt_number.')' : '' }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="removeLeg({{ $i }})" class="text-gray-400 hover:text-red-600" title="Quitar tramo">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                @endforeach

                @if (count($legs) < \App\Services\EventLineupService::MAX_LEGS)
                    <button type="button" wire:click="addLeg" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">+ Agregar tramo</button>
                @endif

                @if ($runners->isEmpty())
                    <p class="text-sm text-amber-600">El equipo no tiene jugadores habilitados en su plantel.</p>
                @endif

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
