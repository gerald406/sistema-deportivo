{{-- Modo carnets: la seleccion vive en Alpine (ids unicos: agregar un id ya
     presente no lo duplica, aunque la misma fila existiera dos veces en el DOM,
     p. ej. si se agrega una vista movil). Sobrevive a paginar/filtrar porque
     Livewire conserva el estado Alpine al re-renderizar. El PDF se pide por POST
     (ids en el body, no en la URL) y se descarga como blob, igual que la referencia. --}}
<div class="space-y-6"
     x-data="{
         selected: [],
         busy: false,
         toggle(id, on) {
             this.selected = this.selected.filter(x => x !== id);
             if (on) this.selected.push(id);
         },
         pageIds() {
             return [...$root.querySelectorAll('[data-card-id]')].map(el => Number(el.dataset.cardId));
         },
         allOnPage() {
             const ids = this.pageIds();
             return ids.length > 0 && ids.every(id => this.selected.includes(id));
         },
         togglePage(on) {
             this.pageIds().forEach(id => this.toggle(id, on));
         },
         generate() {
             const ids = [...new Set(this.selected)];
             if (ids.length === 0 || this.busy) return;
             confirmAction({
                 title: `¿Generar ${ids.length} carnet(s)?`,
                 text: 'Se descargará un PDF con los carnets seleccionados.',
                 confirmText: 'Sí, generar',
             }, async () => {
                 this.busy = true;
                 try {
                     const body = new FormData();
                     body.append('ids', ids.join(','));
                     const res = await fetch(@js(route('cards.massive.post')), {
                         method: 'POST',
                         headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/pdf' },
                         body,
                     });
                     if (! res.ok) {
                         const msg = res.status === 403 ? 'No tienes permiso para alguno de los carnets seleccionados.'
                             : res.status === 422 ? 'Selección inválida (máximo 150 carnets por PDF).'
                             : 'No se pudieron generar los carnets.';
                         Swal.fire({ icon: 'error', titleText: 'Error', text: msg });
                         return;
                     }
                     const url = URL.createObjectURL(await res.blob());
                     const a = Object.assign(document.createElement('a'), { href: url, download: 'fotochecks_masivos.pdf' });
                     document.body.appendChild(a); a.click(); a.remove();
                     URL.revokeObjectURL(url);
                 } catch (e) {
                     Swal.fire({ icon: 'error', titleText: 'Error', text: 'Ocurrió un problema al comunicarse con el servidor.' });
                 } finally {
                     this.busy = false;
                 }
             });
         },
     }">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Planteles</h1>
            <p class="text-sm text-gray-500">
                {{ $cardMode ? 'Modo carnets: marca los participantes y genera sus fotochecks en un solo PDF.' : 'Participantes de cada delegación en cada competencia. Elige la competencia y agrega a tus participantes.' }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($cardMode)
                <button type="button" x-show="selected.length > 0" x-cloak x-on:click="generate()" x-bind:disabled="busy"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-60">
                    <i class="fa-solid" x-bind:class="busy ? 'fa-spinner fa-spin' : 'fa-id-card'"></i>
                    Generar seleccionados (<span x-text="selected.length"></span>)
                </button>
                <button type="button" x-show="selected.length > 0" x-cloak x-on:click="selected = []"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                    Limpiar
                </button>
                <button type="button" wire:click="$set('cardMode', false)"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-xmark"></i> Salir de carnets
                </button>
            @else
                <button type="button" wire:click="$set('cardMode', true)"
                        class="inline-flex items-center gap-2 rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
                    <i class="fa-solid fa-id-card"></i> Carnets
                </button>

                @can('create', \App\Models\SeasonTeamPlayer::class)
                    <button wire:click="openBulk" type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                        <i class="fa-solid fa-users-line"></i> Agregar varios
                    </button>
                    <button wire:click="create" type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        <i class="fa-solid fa-user-plus"></i> Agregar participante
                    </button>
                @endcan
            @endif
        </div>
    </div>

    {{-- Filtros --}}
    {{-- Cuadricula: el select de planteles (delegacion — competencia) desbordaba en fila. --}}
    <div class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:grid-cols-2 xl:grid-cols-6">
        <div class="relative min-w-0 sm:col-span-2 xl:col-span-2">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" wire:model.live.debounce.400ms="search"
                   placeholder="Buscar participante o DNI..."
                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <select wire:model.live="seasonTeamFilter" title="Plantel"
                class="w-full min-w-0 truncate rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 sm:col-span-2 xl:col-span-2">
            <option value="">Todos los planteles</option>
            @foreach ($filterSeasonTeams->groupBy(fn ($st) => $st->team->name) as $teamName => $group)
                <optgroup label="{{ $teamName }}">
                    @foreach ($group as $st)
                        <option value="{{ $st->id }}">{{ $st->season->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>

        <select wire:model.live="rosterStatusFilter" title="Estado"
                class="w-full min-w-0 rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 xl:col-span-1">
            <option value="all">Todos los estados</option>
            @foreach ($this->statusOptions() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>

        <div class="flex min-w-0 gap-3 xl:col-span-1">
            <select wire:model.live="perPage" title="Filas por página"
                    class="w-full min-w-0 flex-1 rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="10">10 por página</option>
                <option value="15">15 por página</option>
                <option value="25">25 por página</option>
                <option value="50">50 por página</option>
            </select>
            <x-export-button />
        </div>
    </div>

    {{-- Tabla --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @if ($cardMode)
                            <th class="w-10 px-4 py-3">
                                <input type="checkbox" title="Seleccionar los de esta página"
                                       x-bind:checked="allOnPage()" x-on:change="togglePage($event.target.checked)"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </th>
                        @endif
                        <x-sortable-th field="shirt_number" label="N°" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="player_last_name" label="Participante" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Delegación / Competencia</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Posición</th>
                        <x-sortable-th field="status" label="Estado" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <x-sortable-th field="enrolled_at" label="Alta" :sort-field="$sortField" :sort-direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($entries as $entry)
                        @php($canPrint = auth()->user()->can('printCard', $entry))
                        {{-- Edicion en linea (D23): fuera del modo carnets y con permiso de editar la ficha --}}
                        @php($inline = ! $cardMode && auth()->user()->can('update', $entry))
                        {{-- updated_at en la clave: tras guardar (o un error) la fila se redibuja con el dato real --}}
                        <tr wire:key="roster-{{ $entry->id }}-{{ $entry->updated_at?->timestamp }}">
                            @if ($cardMode)
                                <td class="px-4 py-3">
                                    @if ($canPrint)
                                        <input type="checkbox" data-card-id="{{ $entry->id }}"
                                               x-bind:checked="selected.includes({{ $entry->id }})"
                                               x-on:change="toggle({{ $entry->id }}, $event.target.checked)"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3 font-semibold text-gray-700">
                                @if ($inline)
                                    <input type="number" min="0" max="999" value="{{ $entry->shirt_number }}" placeholder="—"
                                           aria-label="Camiseta de {{ $entry->player->fullName() }}"
                                           wire:change="updateInline({{ $entry->id }}, 'shirt_number', $event.target.value)"
                                           class="w-16 rounded-md border-gray-300 px-2 py-1 text-center text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @else
                                    {{ $entry->shirt_number ?? '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img class="h-8 w-8 rounded-full border object-cover"
                                         src="{{ $entry->player->photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($entry->player->photo_path) : 'https://ui-avatars.com/api/?name='.urlencode($entry->player->fullName()).'&background=6366f1&color=fff' }}"
                                         alt="{{ $entry->player->fullName() }}">
                                    <div>
                                        <p class="font-medium text-gray-900">
                                            {{ $entry->player->last_name }}, {{ $entry->player->first_name }}
                                            @if ($inline)
                                                {{-- Un capitan por plantel: marcar aqui se la quita al anterior --}}
                                                <button type="button" wire:click="updateInline({{ $entry->id }}, 'is_captain', {{ $entry->is_captain ? 0 : 1 }})"
                                                        title="{{ $entry->is_captain ? 'Quitar capitanía' : 'Hacer capitán' }}"
                                                        class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold {{ $entry->is_captain ? 'bg-indigo-600 text-white' : 'border border-dashed border-gray-300 text-gray-400 hover:border-indigo-400 hover:text-indigo-600' }}">C</button>
                                            @elseif ($entry->is_captain)
                                                <span class="ml-1 rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold text-white" title="Capitán">C</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            DNI {{ $entry->player->dni }}
                                            @if ($entry->player->documents_verified_at)
                                                <span class="ml-1 text-emerald-600" title="Documentos verificados"><i class="fa-solid fa-circle-check"></i></span>
                                            @endif
                                        </p>
                                        {{-- Reglas de reglamento que no cumple (RosterRuleService) --}}
                                        @foreach ($notices[$entry->id] ?? [] as $notice)
                                            <p class="mt-0.5 text-xs text-amber-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $notice }}</p>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <p>{{ $entry->seasonTeam->team->name }}</p>
                                <p class="text-xs text-gray-400">{{ $entry->seasonTeam->season->name }}</p>
                                @if (($missing[$entry->season_team_id] ?? 0) > 0)
                                    <p class="mt-0.5 text-xs text-amber-700">
                                        <i class="fa-solid fa-user-group mr-1"></i>{{ $missing[$entry->season_team_id] === 1 ? 'Falta 1' : 'Faltan '.$missing[$entry->season_team_id] }} para el mínimo
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                @php($sportPositions = $positionsBySport[$entry->seasonTeam->season->sport_id] ?? collect())
                                @if ($inline && $sportPositions->isNotEmpty())
                                    <select wire:change="updateInline({{ $entry->id }}, 'position_id', $event.target.value)"
                                            aria-label="Posición de {{ $entry->player->fullName() }}"
                                            class="w-full min-w-[8rem] rounded-md border-gray-300 py-1 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">—</option>
                                        @foreach ($sportPositions as $position)
                                            <option value="{{ $position->id }}" @selected($entry->position_id === $position->id)>{{ $position->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    {{ $entry->position?->name ?? '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $entry->status->badgeClasses() }}">
                                    {{ $entry->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $entry->enrolled_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @if ($canPrint)
                                        <a href="{{ route('cards.single', $entry) }}" target="_blank" rel="noopener"
                                           class="text-gray-400 hover:text-emerald-600" title="Carnet (PDF)">
                                            <i class="fa-solid fa-id-card"></i>
                                        </a>
                                    @endif
                                    @can('update', $entry)
                                        <button wire:click="edit({{ $entry->id }})" class="text-gray-400 hover:text-indigo-600" title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    @endcan
                                    @can('delete', $entry)
                                        <button type="button" class="text-gray-400 hover:text-red-600" title="Quitar del plantel"
                                                x-on:click="confirmDelete(@js($entry->player->fullName()), () => $wire.delete({{ $entry->id }}))">
                                            <i class="fa-solid fa-user-minus"></i>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $cardMode ? 8 : 7 }}" class="px-4 py-10 text-center text-gray-400">No hay participantes que coincidan con el filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-3">
            {{ $entries->links() }}
        </div>
    </div>

    {{-- Modal crear/editar --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeModal()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $editingId ? 'Editar ficha de plantel' : 'Agregar participante al plantel' }}
            </h2>

            <form wire:submit="save" class="mt-4 space-y-4">
                @if ($editingId)
                    @php($player = $this->selectedPlayer())
                    <div class="rounded-lg bg-gray-50 p-3 text-sm">
                        <p class="font-medium text-gray-900">{{ $player?->fullName() }}</p>
                        <p class="text-gray-500">DNI {{ $player?->dni }}</p>
                    </div>
                @else
                    @php($competitions = $this->competitionOptions())
                    @php($delegations = $this->delegationOptions())
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Competencia</label>
                        <select wire:model.live="form.season_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecciona una competencia</option>
                            @foreach ($competitions->groupBy(fn ($c) => $c->gamesEdition->name) as $editionName => $group)
                                <optgroup label="{{ $editionName }}">
                                    @foreach ($group as $competition)
                                        <option value="{{ $competition->id }}">{{ $competition->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('form.season_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @if ($competitions->isEmpty())
                            <p class="mt-1 text-xs text-amber-700">No hay competencias abiertas para tus delegaciones. El organizador debe generarlas e inscribir tu delegación en la olimpiada.</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Delegación</label>
                        <select wire:model.live="form.team_id" @disabled($form['season_id'] === '')
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                            <option value="">{{ $form['season_id'] === '' ? 'Primero elige la competencia' : 'Selecciona una delegación' }}</option>
                            @foreach ($delegations as $delegation)
                                <option value="{{ $delegation->id }}">{{ $delegation->name }}</option>
                            @endforeach
                        </select>
                        @error('form.team_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Participante</label>
                        @if ($player = $this->selectedPlayer())
                            <div class="mt-1 flex items-center justify-between rounded-lg bg-indigo-50 px-3 py-2 text-sm">
                                <span class="font-medium text-indigo-900">{{ $player->fullName() }} <span class="text-indigo-500">· DNI {{ $player->dni }}</span></span>
                                <button type="button" wire:click="$set('form.player_id', '')" class="text-indigo-400 hover:text-indigo-700" title="Cambiar">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        @else
                            <input type="text" wire:model.live.debounce.300ms="playerSearch" placeholder="Buscar por apellido, nombre o DNI (mín. 2 letras)"
                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @if ($playerSearch !== '')
                                <div class="mt-1 max-h-40 overflow-y-auto rounded-lg border border-gray-200">
                                    @forelse ($this->playerOptions() as $option)
                                        <button type="button" wire:click="selectPlayer({{ $option->id }})" wire:key="opt-{{ $option->id }}"
                                                class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50">
                                            {{ $option->last_name }}, {{ $option->first_name }}
                                            <span class="text-gray-400">· DNI {{ $option->dni }} · {{ $option->birth_date?->format('d/m/Y') }}</span>
                                        </button>
                                    @empty
                                        <p class="px-3 py-2 text-sm text-gray-400">Sin resultados.</p>
                                    @endforelse
                                </div>
                            @endif
                        @endif
                        @error('form.player_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                        {{-- Aviso previo: reglas que incumple en esta competencia --}}
                        @php($check = $this->selectionCheck())
                        @foreach ($check['errors'] as $message)
                            <p class="mt-1 text-xs text-red-600"><i class="fa-solid fa-ban mr-1"></i>{{ $message }} No se podrá inscribir.</p>
                        @endforeach
                        @foreach ($check['warnings'] as $message)
                            <p class="mt-1 text-xs text-amber-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $message }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">N° camiseta</label>
                        <input type="number" min="0" max="999" wire:model="form.shirt_number"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.shirt_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Posición</label>
                        <select wire:model="form.position_id"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Sin posición</option>
                            @foreach ($this->positionOptions() as $position)
                                <option value="{{ $position->id }}">{{ $position->name }}</option>
                            @endforeach
                        </select>
                        @error('form.position_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estado</label>
                        <select wire:model="form.status"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($this->statusOptions() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('form.status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha de alta</label>
                        <input type="date" wire:model="form.enrolled_at"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('form.enrolled_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="form.is_captain" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Capitán del equipo</span>
                    </label>
                    @error('form.is_captain') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Observaciones</label>
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
    {{-- Agregar varios (D23): seleccion multiple con la aptitud de cada participante a la vista --}}
    <div x-data="{ show: @entangle('showBulk') }" x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="show" x-transition.opacity @click="$wire.closeBulk()" class="fixed inset-0 bg-gray-900/50"></div>

        <div x-show="show" x-transition class="relative flex max-h-[92vh] w-full max-w-4xl flex-col rounded-xl bg-white shadow-xl">
            @if ($showBulk)
                @php($bulkSeason = $this->bulkSeason())
                @php($bulkTeamModel = $this->bulkTeam())
                @php($ready = $bulkSeason && $bulkTeamModel)
                @php($bulkSeasonTeam = $ready ? $this->bulkSeasonTeam() : null)
                @php($candidates = $ready ? $this->bulkCandidates() : collect())
                @php($current = $bulkSeasonTeam ? $bulkSeasonTeam->roster()->where('status', '!=', \App\Enums\SeasonTeamPlayerStatus::Inactive->value)->count() : 0)
                @php($max = $bulkSeason?->event?->max_players)
                @php($min = $bulkSeason?->event?->min_players)

                <div class="border-b px-6 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Agregar participantes</h2>
                            <p class="text-sm text-gray-500">Elige la disciplina y la delegación, marca a los participantes y agrégalos de una vez.</p>
                        </div>
                        @if ($ready)
                            <div class="shrink-0 text-right text-sm">
                                <p class="font-semibold text-gray-800">Plantel {{ $current }}{{ $max ? ' / '.$max : '' }}</p>
                                @if ($min && $current < $min)
                                    <p class="text-xs text-amber-700">Faltan {{ $min - $current }} para el mínimo ({{ $min }})</p>
                                @endif
                                @unless ($bulkSeasonTeam)
                                    <p class="text-xs text-indigo-600">Aún no inscrita: se inscribe al agregar</p>
                                @endunless
                            </div>
                        @endif
                    </div>

                    {{-- Destino del lote: disciplina y delegacion (precargadas si habia un plantel filtrado) --}}
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <select wire:model.live="bulkSeasonId" title="Disciplina" aria-label="Disciplina"
                                class="w-full min-w-0 truncate rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Elige la disciplina...</option>
                            @foreach ($this->bulkSeasonOptions() as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="bulkTeamId" title="Delegación" aria-label="Delegación" @disabled(! $bulkSeason)
                                class="w-full min-w-0 truncate rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                            <option value="">{{ $bulkSeason ? 'Elige la delegación...' : 'Primero la disciplina' }}</option>
                            @foreach ($this->bulkTeamOptions() as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($ready)

                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="relative min-w-0 lg:col-span-2">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" wire:model.live.debounce.300ms="bulkSearch" placeholder="Buscar nombre o DNI..."
                                   class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <select wire:model.live="bulkStaffFilter" class="w-full min-w-0 rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Docentes y administrativos</option>
                            @foreach ($this->staffTypeOptions() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="bulkEmploymentFilter" class="w-full min-w-0 rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Nombrados y contratados</option>
                            @foreach ($this->employmentTypeOptions() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                        <button type="button" wire:click="selectAllEligible" class="font-medium text-indigo-600 hover:text-indigo-500">
                            <i class="fa-solid fa-check-double mr-1"></i> Seleccionar todos los aptos ({{ $candidates->where('state', 'ok')->count() }})
                        </button>
                        @if (count($bulkSelected) > 0)
                            <button type="button" wire:click="$set('bulkSelected', [])" class="text-gray-500 hover:text-gray-700">Quitar selección</button>
                        @endif
                        <label class="ml-auto flex items-center gap-2 text-gray-600">
                            <input type="checkbox" wire:model.live="bulkOnlyEligible" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            Solo aptos
                        </label>
                    </div>
                    @endif
                </div>

                {{-- Resumen del ultimo agregado: los validos entraron; el resto, con su motivo --}}
                @if ($bulkResult)
                    <div class="border-b bg-gray-50 px-6 py-3 text-sm">
                        <p class="font-semibold text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i> Se agregaron {{ count($bulkResult['added']) }}.</p>
                        @if ($bulkResult['rejected'] !== [])
                            <p class="mt-1 font-semibold text-red-700"><i class="fa-solid fa-circle-xmark mr-1"></i> No se agregaron {{ count($bulkResult['rejected']) }}:</p>
                            <ul class="ml-5 list-disc text-xs text-red-700">
                                @foreach ($bulkResult['rejected'] as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($bulkResult['warnings'] !== [])
                            <p class="mt-1 font-semibold text-amber-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Agregados con observaciones:</p>
                            <ul class="ml-5 list-disc text-xs text-amber-700">
                                @foreach ($bulkResult['warnings'] as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <div class="flex-1 overflow-y-auto">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($candidates as $candidate)
                                @php($p = $candidate->player)
                                <tr wire:key="bulk-{{ $p->id }}" class="{{ $candidate->state === 'blocked' ? 'bg-gray-50 text-gray-400' : '' }}">
                                    <td class="w-10 px-4 py-2">
                                        <input type="checkbox" value="{{ $p->id }}" wire:model.live="bulkSelected" @disabled($candidate->state === 'blocked')
                                               aria-label="Seleccionar a {{ $p->fullName() }}"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-40">
                                    </td>
                                    <td class="px-2 py-2">
                                        <p class="font-medium {{ $candidate->state === 'blocked' ? '' : 'text-gray-900' }}">{{ $p->last_name }}, {{ $p->first_name }}</p>
                                        <p class="text-xs text-gray-400">
                                            DNI {{ $p->dni }}
                                            @if ($p->birth_date) · {{ (int) $bulkSeason->gamesEdition?->start_date?->year - $p->birth_date->year }} años @endif
                                            · {{ collect([$p->staff_type?->label(), $p->employment_type?->label()])->filter()->implode(' · ') ?: 'Sin tipo' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        @if ($candidate->state === 'ok')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700"><i class="fa-solid fa-check"></i> Apto</span>
                                        @elseif ($candidate->state === 'warn')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700"><i class="fa-solid fa-triangle-exclamation"></i> Con observaciones</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700"><i class="fa-solid fa-ban"></i> No puede</span>
                                        @endif
                                        @foreach ($candidate->reasons as $reason)
                                            <p class="mt-0.5 text-xs {{ $candidate->state === 'warn' ? 'text-amber-700' : 'text-red-600' }}">{{ $reason }}</p>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-6 py-10 text-center text-gray-400">
                                        @if ($ready)
                                            No hay participantes disponibles con estos filtros. Regístralos primero en Participantes.
                                        @else
                                            Elige la disciplina y la delegación para ver a los participantes.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-600">
                        Seleccionados: <span class="font-semibold">{{ count($bulkSelected) }}</span>
                        @if ($max)
                            · Quedan {{ max(0, $max - $current) }} cupos
                            @if (count($bulkSelected) > max(0, $max - $current))
                                <span class="text-amber-700">(la selección supera el tope)</span>
                            @endif
                        @endif
                    </p>
                    <div class="flex gap-3">
                        <button type="button" wire:click="closeBulk" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cerrar</button>
                        <button type="button" wire:click="addSelected" wire:loading.attr="disabled" @disabled(! $ready || count($bulkSelected) === 0)
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50">
                            <span wire:loading.remove wire:target="addSelected"><i class="fa-solid fa-user-plus"></i> Agregar {{ count($bulkSelected) }} al plantel</span>
                            <span wire:loading wire:target="addSelected"><i class="fa-solid fa-spinner fa-spin"></i> Agregando...</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
