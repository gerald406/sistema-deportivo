<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Reportes</h1>
        <p class="text-sm text-gray-500">Tabla de posiciones y medallero. Se actualizan solos al cerrar o reabrir un resultado.</p>
    </div>

    {{-- Tabla de posiciones --}}
    <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Tabla de posiciones</h2>
                @if ($season && $scoring)
                    <p class="text-xs text-gray-500">
                        {{ $season->tournament->name }} · {{ $season->sport->name }} — {{ $scoring->phase_name }}:
                        victoria {{ $scoring->pts_win }}, empate {{ $scoring->pts_draw }}, derrota {{ $scoring->pts_loss }}, walkover {{ $scoring->pts_walkover }}.
                        Solo partidos de fase regular.
                    </p>
                @endif
            </div>
            <select wire:model.live="seasonId"
                    class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @forelse ($this->seasonOptions() as $option)
                    <option value="{{ $option->id }}">{{ $option->tournament->name }} — {{ $option->name }} ({{ $option->sport->name }})</option>
                @empty
                    <option value="">Sin tablas calculadas</option>
                @endforelse
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 text-left">#</th>
                        <th class="px-4 py-2 text-left">Equipo</th>
                        <th class="px-3 py-2 text-right" title="Partidos jugados">PJ</th>
                        <th class="px-3 py-2 text-right" title="Ganados">G</th>
                        <th class="px-3 py-2 text-right" title="Empatados">E</th>
                        <th class="px-3 py-2 text-right" title="Perdidos">P</th>
                        <th class="px-3 py-2 text-right" title="Walkovers en contra">WO</th>
                        <th class="px-3 py-2 text-right" title="A favor">AF</th>
                        <th class="px-3 py-2 text-right" title="En contra">EC</th>
                        <th class="px-3 py-2 text-right" title="Diferencia">Dif</th>
                        <th class="px-4 py-2 text-right">Pts</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 tabular-nums">
                    @forelse ($table as $i => $row)
                        <tr wire:key="standing-{{ $row->id }}">
                            <td class="px-4 py-2 text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $row->seasonTeam->team->name }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $row->matches_played }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $row->wins }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $row->draws }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $row->losses }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $row->walkovers_against }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $row->extra_stats['scored'] ?? 0 }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $row->extra_stats['conceded'] ?? 0 }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ sprintf('%+d', $row->extra_stats['diff'] ?? 0) }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-gray-900">{{ $row->points }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-10 text-center text-gray-400">Todavía no hay una tabla calculada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Medallero --}}
    <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Medallero</h2>
                <p class="text-xs text-gray-500">Orden olímpico: oros, luego platas, luego bronces.</p>
            </div>
            <select wire:model.live="editionId"
                    class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @forelse ($this->editionOptions() as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @empty
                    <option value="">Sin ediciones</option>
                @endforelse
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2 text-left">#</th>
                        <th class="px-4 py-2 text-left">Equipo</th>
                        {{-- La medalla se nombra en texto; el icono de color es solo apoyo. --}}
                        <th class="px-3 py-2 text-right"><i class="fa-solid fa-medal text-amber-500"></i> Oro</th>
                        <th class="px-3 py-2 text-right"><i class="fa-solid fa-medal text-gray-400"></i> Plata</th>
                        <th class="px-3 py-2 text-right"><i class="fa-solid fa-medal text-orange-700"></i> Bronce</th>
                        <th class="px-4 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 tabular-nums">
                    @forelse ($medalTable as $i => $row)
                        <tr wire:key="medal-{{ $row->id }}">
                            <td class="px-4 py-2 text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $row->team->name }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $row->gold }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $row->silver }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ $row->bronze }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-gray-900">{{ $row->gold + $row->silver + $row->bronze }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ $edition ? 'Esta edición aún no tiene medallas.' : 'No hay ediciones registradas.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
