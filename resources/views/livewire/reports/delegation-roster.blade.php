<div class="space-y-6" x-data="{ resolution: null }">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Padrón por delegación</h1>
        <p class="text-sm text-gray-500">
            Cada persona una sola vez por delegación, con todas sus disciplinas y su resolución (PDF), si la adjuntaron.
        </p>
    </div>

    <x-edition-picker :options="$this->editionOptions()" />

    @if (! $edition)
        <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
            No tienes ninguna olimpiada a tu cargo.
        </div>
    @else
        @php
            $groups = $rows->groupBy(fn ($r) => $r->team->id);
            $withResolution = $rows->filter(fn ($r) => $r->player->resolution_path)->count();
        @endphp

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                ['Delegaciones', $groups->count(), 'fa-flag', 'text-indigo-600'],
                ['Personas', $rows->count(), 'fa-users', 'text-emerald-600'],
                ['Con resolución', $withResolution, 'fa-file-pdf', 'text-sky-600'],
                ['Sin resolución', $rows->count() - $withResolution, 'fa-triangle-exclamation', $rows->count() > $withResolution ? 'text-amber-500' : 'text-emerald-600'],
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
                       placeholder="Buscar participante o DNI..."
                       class="w-full rounded-lg border-gray-300 py-2 pl-9 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <select wire:model.live="teamId"
                    class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todas las delegaciones</option>
                @foreach ($this->delegationOptions() as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>

            <x-export-button />
        </div>

        <div class="space-y-4">
            @forelse ($groups as $teamRows)
                <div wire:key="delegation-roster-{{ $teamRows->first()->team->id }}"
                     class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                        <p class="font-semibold text-gray-900">{{ $teamRows->first()->team->name }}</p>
                        <span class="text-sm text-gray-500">
                            {{ $teamRows->count() }} {{ $teamRows->count() === 1 ? 'persona' : 'personas' }}
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
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo / condición</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Disciplinas</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Documentos</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Resolución</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($teamRows as $row)
                                    <tr wire:key="delegation-roster-{{ $row->team->id }}-{{ $row->player->id }}" class="hover:bg-gray-50">
                                        <td class="px-4 py-2 tabular-nums text-gray-500">{{ $loop->iteration }}</td>
                                        <td class="px-4 py-2 text-gray-900">{{ $row->player->last_name }}, {{ $row->player->first_name }}</td>
                                        <td class="px-4 py-2 font-mono text-gray-600">{{ $row->player->dni }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ $row->age ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-600">{{ match ($row->player->gender) { \App\Enums\Gender::Male => 'Varón', \App\Enums\Gender::Female => 'Dama', default => '—' } }}</td>
                                        <td class="px-4 py-2 text-gray-600">
                                            {{ $row->player->staff_type?->label() ?? '—' }}
                                            @if ($row->player->employment_type)
                                                <span class="block text-xs text-gray-400">{{ $row->player->employment_type->label() }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($row->disciplines as $discipline)
                                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $discipline }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-4 py-2">
                                            @if ($row->player->documents_verified_at)
                                                <span class="whitespace-nowrap text-xs text-emerald-600"><i class="fa-solid fa-circle-check"></i> Verificados</span>
                                            @else
                                                <span class="whitespace-nowrap text-xs text-amber-600"><i class="fa-regular fa-clock"></i> Pendientes</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2"><x-resolution-button :player="$row->player" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
                    {{ $search !== '' ? 'Nada coincide con la búsqueda.' : 'Aún no hay participantes en los planteles de esta olimpiada.' }}
                </div>
            @endforelse
        </div>
    @endif

    <x-resolution-modal />
</div>
