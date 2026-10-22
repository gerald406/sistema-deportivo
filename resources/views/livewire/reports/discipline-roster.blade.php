<div class="space-y-6" x-data="{ resolution: null }">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Padrón por disciplina</h1>
        <p class="text-sm text-gray-500">
            Participantes de cada competencia, agrupados por delegación. Elige un deporte y, si quieres, una de sus competencias.
        </p>
    </div>

    <x-edition-picker :options="$this->editionOptions()" />

    @if (! $edition)
        <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
            No tienes ninguna olimpiada a tu cargo.
        </div>
    @else
        @php
            $competitions = $rows->groupBy(fn ($e) => $e->seasonTeam->season_id);
            $people = $rows->pluck('player_id')->unique()->count();
        @endphp

        <div class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:grid-cols-2 lg:grid-cols-4">
            <select wire:model.live="sportId"
                    class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos los deportes</option>
                @foreach ($this->sportOptions() as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="seasonId" @disabled($sportId === '')
                    class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-400">
                <option value="">{{ $sportId === '' ? 'Elige un deporte' : 'Todas sus competencias' }}</option>
                @foreach ($this->seasonOptions() as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="teamId"
                    class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todas las delegaciones</option>
                @foreach ($this->delegationOptions() as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>

            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Participante o DNI..."
                       class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @if (! $sportChosen)
                <p class="text-sm text-gray-500">El Excel trae el padrón de todas las disciplinas.</p>
            @else
                <p class="text-sm text-gray-500">
                    {{ $competitions->count() }} {{ $competitions->count() === 1 ? 'competencia' : 'competencias' }}
                    · {{ $rows->count() }} {{ $rows->count() === 1 ? 'inscripción' : 'inscripciones' }} en planteles
                    · {{ $people }} {{ $people === 1 ? 'persona' : 'personas' }}
                </p>
            @endif
            <x-export-button />
        </div>

        @if (! $sportChosen)
            <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
                <i class="fa-solid fa-filter mb-2 text-2xl text-indigo-300"></i>
                <p>Elige un deporte para ver su padrón.</p>
            </div>
        @else
        <div class="space-y-4">
            @forelse ($competitions as $entries)
                @php $season = $entries->first()->seasonTeam->season; @endphp
                <div wire:key="discipline-roster-{{ $season->id }}"
                     class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                        <p class="font-semibold text-gray-900">{{ $season->name }}</p>
                        <span class="whitespace-nowrap text-sm text-gray-500">
                            {{ $entries->count() }} {{ $entries->count() === 1 ? 'participante' : 'participantes' }}
                        </span>
                    </div>

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
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Resolución</th>
                                </tr>
                            </thead>
                            @foreach ($entries->groupBy(fn ($e) => $e->seasonTeam->team_id) as $teamEntries)
                                <tbody wire:key="discipline-roster-{{ $season->id }}-{{ $teamEntries->first()->seasonTeam->team_id }}" class="divide-y divide-gray-100">
                                    <tr class="bg-gray-50/60">
                                        <td colspan="8" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700">
                                            <i class="fa-solid fa-flag mr-1 text-indigo-500"></i>
                                            {{ $teamEntries->first()->seasonTeam->team->name }}
                                            <span class="font-normal normal-case text-gray-400">· {{ $teamEntries->count() }}</span>
                                        </td>
                                    </tr>
                                    @foreach ($teamEntries as $entry)
                                        <tr wire:key="discipline-roster-entry-{{ $entry->id }}" class="hover:bg-gray-50">
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
                                                    <span class="whitespace-nowrap text-xs text-emerald-600"><i class="fa-solid fa-circle-check"></i> Verificados</span>
                                                @else
                                                    <span class="whitespace-nowrap text-xs text-amber-600"><i class="fa-regular fa-clock"></i> Pendientes</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2"><x-resolution-button :player="$entry->player" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
                    {{ $search !== '' ? 'Nada coincide con la búsqueda.' : 'No hay participantes en los planteles con estos filtros.' }}
                </div>
            @endforelse
        </div>
        @endif
    @endif

    <x-resolution-modal />
</div>
