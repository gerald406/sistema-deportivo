<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Seguimiento de delegaciones</h1>
            <p class="text-sm text-gray-500">
                Personas registradas y disciplinas de cada delegación; en el detalle, el plantel de cada disciplina para cotejarlo con su ficha general.
            </p>
        </div>

        @if ($team)
            <button wire:click="back" type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-arrow-left"></i> Volver al resumen
            </button>
        @endif
    </div>

    <x-edition-picker :options="$this->editionOptions()" />

    @if (! $edition)
        <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
            No tienes ninguna olimpiada ni delegación a tu cargo.
        </div>
    @elseif (! $team)
        {{-- Resumen --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                ['Delegaciones', $totals->delegations, 'fa-flag', 'text-indigo-600'],
                ['Personas registradas', $totals->people, 'fa-users', 'text-emerald-600'],
                ['Inscripciones en disciplinas', $totals->disciplines, 'fa-list-check', 'text-indigo-600'],
                ['Disciplinas sin plantel', $totals->empty, 'fa-triangle-exclamation', $totals->empty > 0 ? 'text-amber-500' : 'text-emerald-600'],
            ] as [$label, $value, $icon, $color])
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        <i class="fa-solid {{ $icon }} mr-1 {{ $color }}"></i> {{ $label }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Buscar delegación..."
                       class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            @if ($manages && $this->groupOptions()->isNotEmpty())
                <select wire:model.live="groupFilter"
                        class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todas las series</option>
                    @foreach ($this->groupOptions() as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            @endif

            <x-export-button />
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Delegación</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Serie</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Personas</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Disciplinas</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Doc. verificados</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Pago</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($rows as $row)
                            <tr wire:key="tracking-{{ $row->entry->id }}" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ $row->entry->team->name }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ $row->entry->delegate?->name ?? 'Sin delegado titular' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $row->entry->group?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <p class="font-semibold text-gray-900">{{ $row->people }}</p>
                                    <p class="text-xs text-gray-400">{{ $row->men }} V · {{ $row->women }} D</p>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <p class="font-semibold text-gray-900">{{ $row->disciplines }}</p>
                                    @if ($row->empty > 0)
                                        <p class="text-xs text-amber-600">{{ $row->empty }} sin plantel</p>
                                    @elseif ($row->disciplines > 0)
                                        <p class="text-xs text-emerald-600">todas con plantel</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                                    {{ $row->verified }} / {{ $row->people }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                                 {{ $row->entry->fee_paid ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $row->entry->fee_paid ? 'Registrado' : 'Pendiente' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $row->entry->status->badgeClasses() }}">
                                        {{ $row->entry->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="show({{ $row->entry->team_id }})" type="button"
                                            class="inline-flex items-center gap-1 whitespace-nowrap text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                        Ver detalle <i class="fa-solid fa-chevron-right text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-gray-400">No hay delegaciones que coincidan con el filtro.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- Detalle de una delegacion --}}
        @php
            $withRoster = $disciplines->where('count', '>', 0)->count();
            $people = $disciplines->flatMap(fn ($d) => $d->players->pluck('player_id'))->unique()->count();
        @endphp

        <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-lg font-semibold text-gray-900">{{ $team->name }}</p>
                <p class="text-sm text-gray-500">
                    {{ $disciplines->count() }} {{ $disciplines->count() === 1 ? 'disciplina' : 'disciplinas' }}
                    · {{ $withRoster }} con plantel · {{ $people }} {{ $people === 1 ? 'persona' : 'personas' }}
                </p>
            </div>

            @if ($withRoster > 0 && $detailSearch === '')
                <a href="{{ route('registrations.general.delegation', [$edition, $team]) }}" target="_blank"
                   class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    <i class="fa-solid fa-file-pdf"></i> Todas las fichas generales
                </a>
            @endif
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" wire:model.live.debounce.400ms="detailSearch"
                       placeholder="Buscar disciplina, participante o DNI..."
                       class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($disciplines as $d)
                @php $season = $d->seasonTeam->season; @endphp
                <div wire:key="discipline-{{ $d->seasonTeam->id }}" x-data="{ open: @js($detailSearch !== '') }"
                     class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
                    <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <button type="button" @click="open = !open" class="flex min-w-0 items-center gap-3 text-left">
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-gray-900">{{ $season->name }}</span>
                                <span class="block text-xs text-gray-400">
                                    {{ $d->kind === \App\Services\DelegationEnrollmentService::TEAM ? 'Colectiva' : 'Individual' }}
                                    @if ($d->min !== null || $d->max !== null)
                                        · mín. {{ $d->min ?? '—' }} / máx. {{ $d->max ?? '—' }}
                                    @endif
                                </span>
                            </span>
                        </button>

                        <div class="flex flex-wrap items-center gap-3">
                            @if ($d->count === 0)
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">Sin plantel</span>
                            @elseif ($d->max !== null && $d->count > $d->max)
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">{{ $d->count }} · excede el tope</span>
                            @elseif ($d->min !== null && $d->count < $d->min)
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">{{ $d->count }} · falta el mínimo</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                    {{ $d->count }} {{ $d->count === 1 ? 'participante' : 'participantes' }}
                                </span>
                            @endif

                            @if ($d->count > 0)
                                <a href="{{ route('registrations.general', $d->seasonTeam) }}" target="_blank"
                                   class="inline-flex items-center gap-1 whitespace-nowrap text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                   title="Ficha general de esta disciplina (PDF)">
                                    <i class="fa-solid fa-file-pdf"></i> Ficha general
                                </a>
                            @endif
                        </div>
                    </div>

                    <div x-show="open" x-cloak class="border-t border-gray-100">
                        @if ($d->players->isEmpty())
                            <p class="px-4 py-6 text-center text-sm text-gray-400">La delegación está inscrita en esta disciplina, pero aún no tiene participantes.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">N.º</th>
                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Apellidos y nombres</th>
                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">DNI</th>
                                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Edad</th>
                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Género</th>
                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Documentos</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($d->players as $entry)
                                            <tr wire:key="tracking-entry-{{ $entry->id }}">
                                                <td class="px-4 py-2 tabular-nums text-gray-600">{{ $entry->shirt_number ?? '—' }}</td>
                                                <td class="px-4 py-2 text-gray-900">
                                                    {{ $entry->player->last_name }}, {{ $entry->player->first_name }}
                                                    @if ($entry->is_captain)
                                                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700" title="Capitán">C</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 font-mono text-gray-600">{{ $entry->player->dni }}</td>
                                                <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ $entry->age ?? '—' }}</td>
                                                <td class="px-4 py-2 text-gray-600">{{ match ($entry->player->gender) { \App\Enums\Gender::Male => 'Varón', \App\Enums\Gender::Female => 'Dama', default => '—' } }}</td>
                                                <td class="px-4 py-2">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $entry->status->badgeClasses() }}">
                                                        {{ $entry->status->label() }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2">
                                                    @if ($entry->player->documents_verified_at)
                                                        <span class="text-xs text-emerald-600"><i class="fa-solid fa-circle-check"></i> Verificados</span>
                                                    @else
                                                        <span class="text-xs text-amber-600"><i class="fa-regular fa-clock"></i> Pendientes</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
                    {{ $detailSearch !== '' ? 'Nada coincide con la búsqueda.' : 'La delegación aún no está inscrita en ninguna disciplina.' }}
                </div>
            @endforelse
        </div>
    @endif
</div>
