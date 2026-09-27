<header class="flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 sm:px-6">
    <div class="flex items-center gap-3">
        {{-- Mobile: abre el sidebar como panel deslizante --}}
        <button @click="sidebarOpen = true" class="text-gray-500 hover:text-gray-700 lg:hidden" aria-label="Abrir menú">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>

        {{-- Desktop: colapsa el sidebar a solo-iconos y viceversa --}}
        <button @click="toggleCollapse()" class="hidden text-gray-500 hover:text-gray-700 lg:block"
                :aria-label="sidebarCollapsed ? 'Mostrar menú completo' : 'Colapsar menú a iconos'">
            <i class="fa-solid text-lg" :class="sidebarCollapsed ? 'fa-angles-right' : 'fa-angles-left'"></i>
        </button>
    </div>

    <div class="hidden text-sm font-medium text-gray-500 lg:block">
        {{ $title ?? '' }}
    </div>

    <div x-data="{ open: false }" class="relative">
        <button @click="open = !open" class="flex items-center gap-2 rounded-full py-1.5 pl-2 pr-3 hover:bg-gray-100">
            <img class="h-8 w-8 rounded-full object-cover" src="{{ auth()->user()->profile_photo_url }}" alt="{{ auth()->user()->name }}">
            <span class="hidden text-sm font-medium text-gray-700 sm:block">{{ auth()->user()->name }}</span>
            <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
        </button>

        <div x-show="open" x-transition @click.outside="open = false" x-cloak
             class="absolute right-0 z-50 mt-2 w-48 rounded-lg bg-white py-1 shadow-lg ring-1 ring-black/5">
            <a href="{{ route('profile.show') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-user w-4"></i> Mi perfil
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-arrow-right-from-bracket w-4"></i> Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</header>
