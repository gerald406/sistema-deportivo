{{-- Visor de la resolucion (PDF). Va dentro de un contenedor con x-data="{ resolution: null }";
     lo abre <x-resolution-button />. El PDF sale de players.resolution, que autoriza cada
     lectura (PlayerPolicy::view). wire:ignore: Livewire no debe tocar el iframe abierto. --}}
<div wire:ignore x-show="resolution" x-cloak x-on:keydown.escape.window="resolution = null"
     class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="resolution" x-transition.opacity x-on:click="resolution = null" class="fixed inset-0 bg-gray-900/50"></div>

    <div x-show="resolution" x-transition
         class="relative flex h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-gray-900">Resolución</h2>
                <p class="truncate text-sm text-gray-500" x-text="resolution?.name"></p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <a :href="resolution?.url" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span class="hidden sm:inline">Abrir en otra pestaña</span>
                </a>
                <button type="button" x-on:click="resolution = null" title="Cerrar"
                        class="rounded-lg px-3 py-2 text-gray-500 hover:bg-gray-100">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <template x-if="resolution">
            <iframe :src="resolution.url" title="Resolución (PDF)" class="w-full flex-1 bg-gray-100"></iframe>
        </template>
    </div>
</div>
