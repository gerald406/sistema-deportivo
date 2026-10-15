<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Inscripción por disciplina</h1>
            <p class="text-sm text-gray-500">Marca en qué disciplinas participa tu delegación y, desde cada una, arma su plantel.</p>
        </div>
        @if ($edition && $team && $summary->enrolled > 0)
            <a href="{{ route('registrations.general.delegation', [$edition, $team]) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                <i class="fa-solid fa-file-pdf"></i> Todas las fichas generales
            </a>
        @endif
    </div>

    {{-- Olimpiada y delegacion --}}
    <div class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:grid-cols-2">
        <div>
            <label for="enr-edition" class="text-sm font-medium text-gray-700"><i class="fa-solid fa-trophy mr-1 text-amber-500"></i> Olimpiada</label>
            <select id="enr-edition" wire:model.live="editionId" class="mt-1 w-full rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @forelse ($this->editionOptions() as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @empty
                    <option value="">No tienes olimpiadas a cargo</option>
                @endforelse
            </select>
        </div>
        <div>
            <label for="enr-team" class="text-sm font-medium text-gray-700"><i class="fa-solid fa-people-group mr-1 text-indigo-500"></i> Delegación</label>
            <select id="enr-team" wire:model.live="teamId" class="mt-1 w-full rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @forelse ($this->teamOptions() as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @empty
                    <option value="">Sin delegaciones habilitadas</option>
                @endforelse
            </select>
        </div>
    </div>

    @if (! $edition || ! $team)
        <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
            No tienes una delegación habilitada para inscribir. Si eres delegado, pide al organizador que te asigne a tu delegación en la olimpiada.
        </div>
    @else
        {{-- Cierre y resumen --}}
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1 rounded-xl p-4 ring-1 {{ $edition->registrationOpen() ? 'bg-emerald-50 ring-emerald-200 text-emerald-800' : 'bg-amber-50 ring-amber-200 text-amber-800' }}">
                <p class="text-sm font-semibold">
                    <i class="fa-solid {{ $edition->registrationOpen() ? 'fa-lock-open' : 'fa-lock' }} mr-1"></i>
                    @if (! $edition->registration_deadline)
                        Inscripciones abiertas
                    @elseif ($edition->registrationOpen())
                        Inscripciones abiertas hasta el {{ $edition->registration_deadline->format('d/m/Y') }}
                    @else
                        Inscripciones cerradas el {{ $edition->registration_deadline->format('d/m/Y') }}
                    @endif
                </p>
                @if (! $canEnroll)
                    <p class="mt-1 text-xs">Ya no puedes marcar ni retirar disciplinas; consúltalo con el organizador.</p>
                @endif
            </div>
            <div class="grid flex-1 grid-cols-3 gap-3 text-center">
                <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-900/5"><p class="text-2xl font-semibold text-gray-900">{{ $summary->enrolled }}</p><p class="text-xs text-gray-500">disciplinas inscritas</p></div>
                <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-900/5"><p class="text-2xl font-semibold text-gray-900">{{ $summary->participants }}</p><p class="text-xs text-gray-500">participaciones</p></div>
                <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-900/5"><p class="text-2xl font-semibold text-gray-900">{{ $summary->total }}</p><p class="text-xs text-gray-500">disciplinas en las bases</p></div>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:grid-cols-2 lg:grid-cols-5">
            <div class="relative lg:col-span-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar disciplina..."
                       class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <select wire:model.live="kindFilter" class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Colectivas e individuales</option>
                <option value="team">Colectivas</option>
                <option value="individual">Individuales</option>
            </select>
            <select wire:model.live="categoryFilter" class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos los niveles</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="genderFilter" class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Damas y varones</option>
                @foreach ($this->genderOptions() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="enrolledFilter" class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Inscritas y no inscritas</option>
                <option value="enrolled">Solo inscritas</option>
                <option value="not_enrolled">Solo no inscritas</option>
            </select>
        </div>

        {{-- Disciplinas --}}
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Disciplina</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Participamos</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Plantel</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            @php($active = (bool) $row->seasonTeam?->is_active)
                            <tr wire:key="enr-{{ $row->season->id }}" class="{{ $active ? 'bg-emerald-50/40' : '' }}">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ $row->season->name }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ $row->season->sport->name }}{{ $row->season->discipline ? ' — '.$row->season->discipline->name : '' }}
                                        · {{ $row->season->category?->name ?? 'Sin nivel' }} · {{ $row->season->gender?->label() ?? 'Sin género' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $row->kind === 'team' ? 'bg-sky-100 text-sky-700' : 'bg-violet-100 text-violet-700' }}">
                                        {{ $row->kind === 'team' ? 'Colectiva' : 'Individual' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($row->seasonTeam && ! $row->seasonTeam->is_active)
                                        <span class="text-xs text-gray-500" title="La organización desactivó esta inscripción">Desactivada</span>
                                    @else
                                        <button type="button" wire:click="toggle({{ $row->season->id }})" wire:loading.attr="disabled" @disabled(! $canEnroll)
                                                role="switch" aria-checked="{{ $active ? 'true' : 'false' }}" aria-label="Participamos en {{ $row->season->name }}"
                                                class="relative inline-flex h-6 w-11 items-center rounded-full transition disabled:cursor-not-allowed disabled:opacity-50 {{ $active ? 'bg-emerald-600' : 'bg-gray-300' }}">
                                            <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition {{ $active ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                                        </button>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    @if ($active)
                                        <span class="font-medium">{{ $row->participants }}</span>{{ $row->max ? ' / '.$row->max : '' }}
                                        @if ($row->min && $row->participants < $row->min)
                                            <span class="ml-1 text-xs text-amber-700" title="Mínimo según el cuadro">⚠ mínimo {{ $row->min }}</span>
                                        @elseif ($row->participants === 0)
                                            <span class="ml-1 text-xs text-amber-700">⚠ sin participantes</span>
                                        @endif
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($active)
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('rosters.index', ['inscripcion' => $row->seasonTeam->id]) }}"
                                               class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                                <i class="fa-solid fa-users"></i> Plantel
                                            </a>
                                            @if ($row->participants > 0)
                                                <a href="{{ route('registrations.general', $row->seasonTeam) }}" target="_blank" rel="noopener"
                                                   class="text-gray-400 hover:text-indigo-600" title="Ficha de inscripción general (PDF)">
                                                    <i class="fa-solid fa-file-pdf"></i>
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-gray-400">No hay disciplinas que coincidan con el filtro.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
