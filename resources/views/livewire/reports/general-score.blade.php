<div class="space-y-6">
    {{-- Al imprimir solo sale la tabla: sin menu, barra superior ni controles. --}}
    <style>
        @media print {
            aside, header, .no-print { display: none !important; }
            main { overflow: visible !important; padding: 0 !important; }
        }
    </style>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Puntaje general</h1>
            <p class="text-sm text-gray-500">
                Cada podio suma a su delegación los puntos de su fila del cuadro. Se actualiza solo al cerrar un resultado o registrar un podio.
            </p>
        </div>

        @if ($edition)
            <div class="no-print flex flex-wrap gap-2">
                @if ($this->canRecalculate())
                    <button wire:click="recalculate" type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fa-solid fa-rotate" wire:loading.class="fa-spin" wire:target="recalculate"></i> Recalcular
                    </button>
                @endif
                <button type="button" x-on:click="window.print()"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    <i class="fa-solid fa-print"></i> Imprimir
                </button>
            </div>
        @endif
    </div>

    @php($options = $this->editionOptions())
    @if ($options->count() > 1)
        <div class="no-print flex flex-col gap-2 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:flex-row sm:items-center">
            <label for="score-edition" class="text-sm font-medium text-gray-700">
                <i class="fa-solid fa-trophy mr-1 text-amber-500"></i> Olimpiada
            </label>
            <select id="score-edition" wire:model.live="editionId"
                    class="flex-1 rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach ($options as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}{{ $option->is_active ? '' : ' (inactiva)' }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if (! $edition)
        <div class="rounded-xl bg-white p-10 text-center text-gray-400 shadow-sm ring-1 ring-gray-900/5">
            No hay ninguna olimpiada que puedas consultar.
        </div>
    @else
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
            <div class="border-b border-gray-100 px-4 py-3">
                <h2 class="text-base font-semibold text-gray-900">{{ $edition->name }}</h2>
                <p class="text-xs text-gray-500">Orden: puntos; a igualdad, más primeros puestos, luego segundos, luego terceros.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Pos.</th>
                            <th class="px-4 py-2 text-left">Delegación</th>
                            <th class="px-4 py-2 text-left">Serie</th>
                            {{-- El puesto se nombra en texto; el icono de color es solo apoyo. --}}
                            <th class="px-3 py-2 text-right"><i class="fa-solid fa-medal text-amber-500"></i> 1.º</th>
                            <th class="px-3 py-2 text-right"><i class="fa-solid fa-medal text-gray-400"></i> 2.º</th>
                            <th class="px-3 py-2 text-right"><i class="fa-solid fa-medal text-orange-700"></i> 3.º</th>
                            <th class="px-4 py-2 text-right">Puntos</th>
                            <th class="no-print px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 tabular-nums">
                        @forelse ($table as $row)
                            @php($isOpen = $openTeam && $openTeam->id === $row->team->id)
                            <tr wire:key="score-{{ $row->team->id }}" @class(['bg-gray-50 text-gray-500' => $row->disqualified])>
                                <td class="px-4 py-2 font-semibold text-gray-700">{{ $row->position ?? '—' }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900">
                                    {{ $row->team->name }}
                                    @if ($row->disqualified)
                                        <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700">Descalificada</span>
                                    @elseif ($row->status === \App\Enums\EditionTeamStatus::Withdrawn)
                                        <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">Retirada</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $row->group?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-right text-gray-700">{{ $row->gold }}</td>
                                <td class="px-3 py-2 text-right text-gray-700">{{ $row->silver }}</td>
                                <td class="px-3 py-2 text-right text-gray-700">{{ $row->bronze }}</td>
                                <td class="px-4 py-2 text-right text-base font-semibold text-gray-900">{{ $row->total_points }}</td>
                                <td class="no-print px-4 py-2 text-right">
                                    @if ($row->gold + $row->silver + $row->bronze > 0)
                                        <button type="button" wire:click="toggleTeam({{ $row->team->id }})" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">
                                            {{ $isOpen ? 'Ocultar' : 'Ver premios' }}
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if ($isOpen)
                                <tr wire:key="score-detail-{{ $row->team->id }}" class="no-print bg-indigo-50/50">
                                    <td></td>
                                    <td colspan="7" class="px-4 py-3">
                                        <ul class="space-y-1 text-sm">
                                            @foreach ($detail as $award)
                                                <li class="flex items-center justify-between gap-4">
                                                    <span><span class="font-semibold text-gray-700">{{ $award->place }}.º</span> · {{ $award->season->name }}</span>
                                                    <span class="font-mono text-gray-700">{{ $award->points }} pts</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">Esta olimpiada aún no tiene delegaciones inscritas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
