<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Reportes</h1>
        <p class="text-sm text-gray-500">Tabla de posiciones de cada competencia. Se actualiza sola al cerrar o reabrir un resultado.</p>
    </div>

    {{-- Tabla de posiciones --}}
    <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Tabla de posiciones</h2>
                @if ($season && $scoring)
                    <p class="text-xs text-gray-500">
                        {{ $season->gamesEdition->name }} · {{ $season->sport->name }} — {{ $scoring->phase_name }}:
                        victoria {{ $scoring->pts_win }}, empate {{ $scoring->pts_draw }}, derrota {{ $scoring->pts_loss }}, walkover {{ $scoring->pts_walkover }}.
                        Solo partidos de fase regular.
                    </p>
                @endif
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <select wire:model.live="seasonId"
                        class="rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @forelse ($this->seasonOptions() as $option)
                        <option value="{{ $option->id }}">{{ $option->gamesEdition->name }} — {{ $option->name }} ({{ $option->sport->name }})</option>
                    @empty
                        <option value="">Sin tablas calculadas</option>
                    @endforelse
                </select>
                <x-export-button />
            </div>
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

    {{-- El medallero ahora es el puntaje general de la olimpiada --}}
    <section class="flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-900/5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Puntaje general</h2>
            <p class="text-xs text-gray-500">Clasificación de las delegaciones: cada podio suma los puntos de su fila del cuadro.</p>
        </div>
        <a href="{{ route('reports.general-score') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
            <i class="fa-solid fa-ranking-star"></i> Ver puntaje general
        </a>
    </section>
</div>
