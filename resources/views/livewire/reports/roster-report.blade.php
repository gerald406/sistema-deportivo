<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <h1 class="flex items-center gap-2 text-2xl font-semibold text-gray-900">
            <i class="fa-solid fa-id-card text-indigo-600"></i>
            Padrón de Participantes
        </h1>
        <p class="mt-1 text-sm text-gray-500">Genera un PDF con el plantel de cada equipo inscrito en una competencia.</p>

        <button wire:click="toggleFilters" type="button"
                class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-gray-100 px-4 py-2 font-medium text-gray-700 transition hover:bg-gray-200 sm:hidden">
            <i class="fa-solid fa-filter"></i>
            <span>{{ $showFilters ? 'Ocultar filtros' : 'Mostrar filtros' }}</span>
        </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-md">
        <div class="space-y-4 p-4 sm:p-6">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">
                    <i class="fa-solid fa-trophy mr-1 text-yellow-500"></i> Olimpiada *
                </label>
                <select wire:model.live="gamesEditionId"
                        class="w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- Seleccione una olimpiada --</option>
                    @foreach ($this->gamesEditions() as $gamesEdition)
                        <option value="{{ $gamesEdition->id }}">{{ $gamesEdition->name }}{{ $gamesEdition->is_active ? '' : ' (inactivo)' }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700">
                    <i class="fa-solid fa-calendar-days mr-1 text-indigo-500"></i> Competencia *
                </label>
                <select wire:model.live="seasonId" @disabled($gamesEditionId === '')
                        class="w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                    <option value="">{{ $gamesEditionId === '' ? 'Primero elige la olimpiada' : '-- Seleccione una competencia --' }}</option>
                    @foreach ($this->seasons() as $season)
                        <option value="{{ $season->id }}">{{ $season->name }} ({{ $season->status->label() }})</option>
                    @endforeach
                </select>
            </div>

            @php($canPrintAll = $this->canPrintAll())

            <div class="{{ $showFilters ? 'block' : 'hidden sm:block' }}">
                <label class="mb-2 block text-sm font-medium text-gray-700">
                    <i class="fa-solid fa-layer-group mr-1 text-emerald-500"></i> Categoría (opcional)
                </label>
                <select wire:model.live="categoryId" @disabled($seasonId === '')
                        class="w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                    <option value="">-- Todas las categorías --</option>
                    @foreach ($this->categories() as $category)
                        <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->gender->label() }})</option>
                    @endforeach
                </select>
            </div>

            <div class="{{ $showFilters || ! $canPrintAll ? 'block' : 'hidden sm:block' }}">
                <label class="mb-2 block text-sm font-medium text-gray-700">
                    {{-- El delegado solo imprime el padron de SU equipo: debe elegirlo. --}}
                    <i class="fa-solid fa-users mr-1 text-blue-500"></i> Equipo {{ $canPrintAll ? '(opcional)' : '*' }}
                </label>
                <select wire:model.live="seasonTeamId" @disabled($seasonId === '')
                        class="w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                    <option value="">{{ $canPrintAll ? '-- Todos los equipos --' : '-- Seleccione su equipo --' }}</option>
                    @foreach ($this->seasonTeams() as $seasonTeam)
                        <option value="{{ $seasonTeam->id }}">{{ $seasonTeam->team->name }}{{ $seasonTeam->category ? ' — '.$seasonTeam->category->name : '' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mt-6 flex justify-end">
                @if ($pdfUrl = $this->pdfUrl())
                    {{-- <a target="_blank"> y no wire:click: Livewire::redirect() siempre navega
                         la pestaña actual (no puede abrir otra). Como el enlace está dentro del
                         render, la URL se recalcula en cada cambio de filtro (wire:model.live). --}}
                    <a href="{{ $pdfUrl }}" target="_blank" rel="noopener"
                       class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 font-bold text-white shadow-lg transition-all duration-200 hover:bg-indigo-700 sm:w-auto">
                        <i class="fa-solid fa-file-pdf text-lg"></i>
                        <span>Generar Padrón PDF</span>
                    </a>
                @else
                    <button type="button" disabled
                            class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-lg bg-gray-300 px-6 py-3 font-bold text-gray-500 sm:w-auto">
                        <i class="fa-solid fa-file-pdf text-lg"></i>
                        {{ $seasonId === '' ? 'Selecciona una competencia' : 'Selecciona tu equipo' }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        <i class="fa-solid fa-circle-info mr-1"></i>
        <strong>El PDF incluirá:</strong>
        <ul class="ml-5 mt-2 list-disc">
            <li>Plantel de cada equipo inscrito (o solo el elegido), ordenado por dorsal</li>
            <li>Documento, apellidos y nombres, fecha de nacimiento y dorsal</li>
            <li>Conteo de participantes activos e inactivos por equipo</li>
            <li>Firmas del delegado del equipo en la competencia y del organizador</li>
        </ul>
    </div>
</div>
