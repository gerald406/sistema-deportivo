<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    {{-- Font Awesome via CDN: mas simple que empaquetarlo con Vite y
         suficiente para iconos de sidebar/dropdowns. Si mas adelante se
         prefiere bundlearlo, se instala con `npm install @fortawesome/fontawesome-free`
         y se importa desde resources/css/app.css en su lugar. --}}
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
          crossorigin="anonymous" referrerpolicy="no-referrer">

    {{-- SweetAlert2: confirmaciones de borrado y toasts de feedback en
         todo el panel administrativo (requerido por el stack del
         proyecto). Se carga antes de Livewire para que ya exista
         `Swal` cuando un componente dispare 'toast'. --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans antialiased text-gray-900"
      x-data="{
          sidebarOpen: false,
          sidebarCollapsed: (localStorage.getItem('sidebarCollapsed') === 'true'),
          toggleCollapse() {
              this.sidebarCollapsed = !this.sidebarCollapsed;
              localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
          }
      }">

    <div class="flex h-full">
        @include('layouts.partials.admin-sidebar')

        {{-- Overlay: solo bloquea/oscurece el contenido en mobile mientras el sidebar esta abierto --}}
        <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
             class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden" x-cloak></div>

        <div class="flex min-w-0 flex-1 flex-col">
            @include('layouts.partials.admin-topbar')

            <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')

    {{-- Listener global: cualquier componente Livewire dispara
         Toast::make(...)->send() -> $this->dispatch('toast', type, message)
         y aqui se traduce a un toast de SweetAlert2, sin repetir este
         bloque en cada vista. --}}
    <script>
        /**
         * Confirmacion de borrado compartida por todos los CRUDs. Uso en
         * Blade (dentro del componente Livewire, via Alpine):
         *   x-on:click="confirmDelete(@js($model->name), () => $wire.delete({{ $model->id }}))"
         *
         * - Es global a proposito: una `function` declarada dentro de
         *   @script NO queda en window (Livewire 3 la evalua con Alpine
         *   como expresion), asi que un onclick="..." no la encuentra.
         * - @js() escapa comillas/HTML para el atributo, y titleText (no
         *   title) evita que SweetAlert interprete el nombre como HTML:
         *   ambos cierran un XSS almacenado via nombres de equipo/jugador.
         */
        window.confirmDelete = (name, onConfirm) => {
            Swal.fire({
                titleText: `¿Eliminar "${name}"?`,
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626',
            }).then((result) => {
                if (result.isConfirmed) {
                    onConfirm();
                }
            });
        };

        document.addEventListener('livewire:init', () => {
            Livewire.on('toast', ({ type, message }) => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: type,
                    titleText: message,
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true,
                });
            });
        });
    </script>
</body>
</html>
