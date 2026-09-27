<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Panel de control</h1>
        <p class="text-sm text-gray-500">
            Bienvenido, {{ auth()->user()->name }}
            <span class="text-gray-400">({{ auth()->user()->getRoleNames()->implode(', ') }})</span>
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @can('tournaments.manage')
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-900/5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600">
                        <i class="fa-solid fa-trophy"></i>
                    </span>
                    <div>
                        <p class="text-sm text-gray-500">Torneos activos</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $activeTournaments }}</p>
                    </div>
                </div>
            </div>
        @endcan

        @can('teams.manage')
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-900/5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                        <i class="fa-solid fa-people-group"></i>
                    </span>
                    <div>
                        <p class="text-sm text-gray-500">Equipos registrados</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $activeTeams }}</p>
                    </div>
                </div>
            </div>
        @endcan
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-900/5">
        <p class="text-sm text-gray-500">
            Este panel es el punto de partida de las Fases 3 en adelante (Equipos y Jugadores,
            Torneos y Temporadas, etc.). Los enlaces del sidebar que aun no tienen una pantalla
            construida aparecen deshabilitados en vez de romper la navegación.
        </p>
    </div>
</div>
