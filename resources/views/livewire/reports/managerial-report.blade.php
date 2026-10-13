<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <h1 class="flex items-center gap-2 text-2xl font-semibold text-gray-900">
            <i class="fa-solid fa-chart-pie text-green-600"></i>
            Reporte Gerencial
        </h1>
        <p class="mt-1 text-sm text-gray-500">Genera un reporte PDF con el estado completo de una competencia.</p>

        <button wire:click="toggleInfo" type="button"
                class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-gray-100 px-4 py-2 font-medium text-gray-700 transition hover:bg-gray-200 sm:hidden">
            <i class="fa-solid fa-circle-info"></i>
            <span>{{ $showInfo ? 'Ocultar secciones' : 'Mostrar secciones' }}</span>
        </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-md">
        <div class="space-y-4 p-4 sm:p-6">
            <div>
                <label for="season" class="mb-2 block text-sm font-medium text-gray-700">
                    <i class="fa-solid fa-trophy mr-1 text-yellow-500"></i> Competencia <span class="text-red-500">*</span>
                </label>
                <select wire:model.live="seasonId" id="season"
                        class="w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- Seleccione una competencia --</option>
                    @foreach ($this->seasons()->groupBy('gamesEdition.name') as $gamesEditionName => $seasons)
                        <optgroup label="{{ $gamesEditionName }}">
                            @foreach ($seasons as $season)
                                <option value="{{ $season->id }}">{{ $season->name }} · {{ $season->sport->name }} ({{ $season->status->label() }})</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="mt-6 flex justify-end">
                @if ($pdfUrl = $this->pdfUrl())
                    {{-- <a target="_blank">: Livewire::redirect() no puede abrir otra pestaña. --}}
                    <a wire:key="btn-managerial-{{ $seasonId }}"
                       wire:loading.class="opacity-50 cursor-wait pointer-events-none"
                       href="{{ $pdfUrl }}" target="_blank" rel="noopener"
                       class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-6 py-3 font-bold text-white shadow-lg transition-all duration-200 hover:bg-green-700 sm:w-auto">
                        <i class="fa-solid fa-file-pdf text-lg"></i>
                        <span>Generar Reporte Gerencial</span>
                        <span wire:loading wire:target="seasonId" class="ml-2"><i class="fa-solid fa-spinner fa-spin"></i></span>
                    </a>
                @else
                    <button type="button" disabled
                            class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-lg bg-gray-300 px-6 py-3 font-bold text-gray-500 sm:w-auto">
                        <i class="fa-solid fa-file-pdf text-lg"></i>
                        Selecciona una competencia
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="{{ $showInfo ? 'block' : 'hidden sm:block' }} rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        <div class="mb-2 flex items-center gap-2">
            <i class="fa-solid fa-list-check"></i>
            <strong>Secciones incluidas en el PDF:</strong>
        </div>
        <ul class="ml-5 grid list-disc grid-cols-1 gap-2 sm:grid-cols-2">
            <li>Resultados de la última jornada completada</li>
            <li>Tabla de posiciones (enfrentamientos)</li>
            <li>Programación de la próxima jornada</li>
            <li>Observaciones de cada jornada</li>
            <li>Tabla de goleadores (si el deporte registra goles)</li>
            <li>Participantes con sanciones pendientes</li>
            <li>Participantes con 2+ tarjetas amarillas</li>
            <li>Firma del organizador</li>
        </ul>
    </div>
</div>
