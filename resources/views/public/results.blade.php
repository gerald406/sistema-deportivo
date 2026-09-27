<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Tabla de posiciones, resultados, programación y estadísticas de los torneos de {{ config('app.name') }}.">
    <title>Resultados - {{ config('app.name') }}</title>

    {{-- Google Fonts: Unbounded (display) + Sora (body) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@400;600;800;900&family=Sora:wght@300;400;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
          crossorigin="anonymous" referrerpolicy="no-referrer">

    {{-- Leaflet por CDN (unpkg), como la referencia: solo lo usa esta pagina, no
         hace falta en el bundle. Con los hashes SRI oficiales de la 1.9.4. --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    {{-- Tailwind compilado (brand-* y fuentes en tailwind.config.js), sin el CDN de la referencia. --}}
    @vite(['resources/css/app.css'])

    <style>
        body { font-family: 'Sora', sans-serif; background-color: #f8fafc; }
        h1, h2, h3, h4, h5, h6, .font-display { font-family: 'Unbounded', sans-serif; }
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #045694; border-radius: 4px; }
    </style>

    @livewireStyles
</head>
<body class="flex min-h-screen flex-col text-slate-800 antialiased selection:bg-brand-primary selection:text-white">

    <nav class="sticky top-0 z-40 border-b border-brand-secondary/30 bg-brand-primary shadow-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
            <a href="{{ route('home') }}" class="group flex items-center gap-3 no-underline">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-md transition-transform duration-700 group-hover:rotate-180">
                    <i class="fa-solid fa-trophy text-xl text-brand-primary"></i>
                </div>
                <span class="font-display text-xl font-bold tracking-wide text-white">{{ config('app.name') }}</span>
            </a>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 rounded-full bg-brand-accent px-5 py-2 font-bold text-white shadow-md transition-all hover:bg-[#e07722]">
                        <i class="fa-solid fa-gauge-high"></i> <span class="hidden md:inline">Dashboard</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="rounded-full bg-white px-6 py-2 font-bold text-brand-primary shadow-md transition-all hover:bg-slate-100">
                        <i class="fa-solid fa-right-to-bracket mr-1"></i> Ingresar
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="mx-auto w-full max-w-6xl flex-grow px-4 py-10">
        <livewire:public.public-results />
    </main>

    <footer class="mt-auto border-t border-slate-200 bg-white py-8 text-center text-sm text-slate-500 shadow-inner">
        <div class="mb-2 font-display text-lg font-bold text-brand-primary">
            <i class="fa-solid fa-trophy text-brand-gold"></i> {{ config('app.name') }}
        </div>
        <p>&copy; {{ date('Y') }} Sistema de Gestión Deportiva Multideporte.</p>
    </footer>

    {{-- Sin boton flotante de WhatsApp: no tenemos ese canal de contacto. --}}

    @livewireScripts
</body>
</html>
