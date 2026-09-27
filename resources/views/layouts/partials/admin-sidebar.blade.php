<aside
    x-cloak
    :class="[
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        sidebarCollapsed ? 'lg:w-20' : 'lg:w-64'
    ]"
    class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col transform bg-gray-900 text-gray-100
           transition-all duration-200 ease-in-out
           lg:static lg:inset-auto lg:translate-x-0 lg:shrink-0"
>
    <div class="flex h-16 items-center justify-between border-b border-gray-800 px-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 overflow-hidden font-semibold text-white">
            <i class="fa-solid fa-medal shrink-0 text-amber-400"></i>
            <span x-show="!sidebarCollapsed" x-transition class="truncate">Gestión Deportiva</span>
        </a>
        <button @click="sidebarOpen = false" class="text-gray-400 hover:text-white lg:hidden" aria-label="Cerrar menú">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>

    {{-- min-h-0 es necesario para que un hijo flex con overflow-y-auto
         realmente haga scroll en vez de estirar el <aside>: sin esto el
         navegador ignora el overflow del <nav> dentro de un contenedor flex. --}}
    <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto overflow-x-hidden px-2 py-4">
        @foreach (\App\Support\Menu\AdminMenu::forUser(auth()->user()) as $item)
            @if (empty($item['children']))
                @php $hasRoute = Route::has($item['route']); @endphp
                <a href="{{ $hasRoute ? route($item['route']) : '#' }}"
                   @if(!$hasRoute) aria-disabled="true" @endif
                   :class="sidebarCollapsed ? 'justify-center' : ''"
                   :title="sidebarCollapsed ? '{{ $item['label'] }}' : ''"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
                          {{ $hasRoute && request()->routeIs($item['route'].'*')
                                ? 'bg-gray-800 text-white'
                                : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}
                          {{ !$hasRoute ? 'opacity-50 cursor-not-allowed' : '' }}">
                    <i class="{{ $item['icon'] }} w-5 shrink-0 text-center"></i>
                    <span x-show="!sidebarCollapsed" x-transition class="truncate">{{ $item['label'] }}</span>
                </a>
            @else
                @php
                    $isOpen = collect($item['children'])->contains(
                        fn ($c) => Route::has($c['route']) && request()->routeIs($c['route'].'*')
                    );
                @endphp
                <div x-data="{ open: {{ $isOpen ? 'true' : 'false' }} }">
                    <button @click="open = !open" type="button"
                            :class="sidebarCollapsed ? 'justify-center' : 'justify-between'"
                            :title="sidebarCollapsed ? '{{ $item['label'] }}' : ''"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white">
                        <span class="flex items-center gap-3 overflow-hidden">
                            <i class="{{ $item['icon'] }} w-5 shrink-0 text-center"></i>
                            <span x-show="!sidebarCollapsed" x-transition class="truncate">{{ $item['label'] }}</span>
                        </span>
                        <i x-show="!sidebarCollapsed"
                           class="fa-solid fa-chevron-down shrink-0 text-xs transition-transform" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    {{-- El submenu no tiene sentido en modo icono-solo (no hay
                         espacio para etiquetas), asi que se oculta junto con
                         el sidebar colapsado en vez de mostrarse como flyout. --}}
                    <div x-show="open && !sidebarCollapsed" x-transition class="mt-1 ml-8 space-y-1">
                        @foreach ($item['children'] as $child)
                            @php $childHasRoute = Route::has($child['route']); @endphp
                            <a href="{{ $childHasRoute ? route($child['route']) : '#' }}"
                               @if(!$childHasRoute) aria-disabled="true" @endif
                               class="block rounded-lg px-3 py-2 text-sm transition
                                      {{ $childHasRoute && request()->routeIs($child['route'].'*')
                                            ? 'bg-gray-800 text-white'
                                            : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}
                                      {{ !$childHasRoute ? 'opacity-50 cursor-not-allowed' : '' }}">
                                {{ $child['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    <div x-show="!sidebarCollapsed" x-transition class="border-t border-gray-800 p-4 text-xs text-gray-500">
        <p class="truncate font-medium text-gray-300">{{ auth()->user()->name }}</p>
        <p class="truncate">{{ auth()->user()->getRoleNames()->implode(', ') }}</p>
    </div>
</aside>
