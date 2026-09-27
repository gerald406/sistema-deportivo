<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ config('app.name') }} - Gestión de torneos multideporte: enfrentamientos, pruebas individuales con ranking y postas. Equipos, planteles, resultados, tablas y reportes en una sola plataforma.">
    <title>{{ config('app.name') }} - Gestión de Torneos Multideporte</title>

    {{-- Google Fonts: Unbounded (display) + Sora (body) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@400;600;800;900&family=Sora:wght@300;400;600;700&display=swap" rel="stylesheet">

    {{-- Font Awesome (misma version e integridad que el panel) --}}
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
          crossorigin="anonymous" referrerpolicy="no-referrer">

    {{-- Tailwind compilado con Vite: la paleta brand-* y las fuentes display/body
         estan en tailwind.config.js (la referencia usaba el CDN de Tailwind). --}}
    @vite(['resources/css/app.css'])

    <style>
        body { font-family: 'Sora', sans-serif; overflow-x: hidden; }
        h1, h2, h3, h4, h5, h6, .font-display { font-family: 'Unbounded', sans-serif; }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #045694; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #077bcb; }

        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
        .animate-marquee { animation: marquee 30s linear infinite; display: flex; width: max-content; }

        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
        .animate-float { animation: float 4s ease-in-out infinite; }

        .reveal { opacity: 0; transform: translateY(40px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        .reveal.active { opacity: 1; transform: translateY(0); }
        .delay-100 { transition-delay: 100ms; }
        .delay-200 { transition-delay: 200ms; }
        .delay-300 { transition-delay: 300ms; }

        .modern-card {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            transition: all 0.4s ease;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
        }
        .modern-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(4, 86, 148, 0.1), 0 8px 10px -6px rgba(4, 86, 148, 0.04);
            border-color: #e2e8f0;
        }

        .fade-enter { opacity: 0; transform: translateY(10px); transition: all 0.4s ease; }
        .fade-enter-active { opacity: 1; transform: translateY(0); }

        @media (prefers-reduced-motion: reduce) {
            .animate-marquee, .animate-float { animation: none; }
            .reveal { opacity: 1; transform: none; transition: none; }
        }
    </style>
</head>
<body class="bg-white text-slate-800 antialiased selection:bg-brand-primary selection:text-white">

    {{-- Sin boton flotante de WhatsApp: la referencia usaba un numero propio y
         este sistema no tiene ese canal de contacto. --}}

    {{-- NAVBAR --}}
    <nav id="navbar" class="fixed z-40 w-full border-b border-brand-secondary/30 bg-brand-primary px-6 py-4 shadow-lg transition-all duration-500 md:px-12">
        <div class="mx-auto flex max-w-7xl items-center justify-between">
            <a href="{{ route('home') }}" class="group flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-md transition-transform duration-700 group-hover:rotate-180">
                    <i class="fa-solid fa-trophy text-xl text-brand-primary"></i>
                </div>
                <span class="font-display text-xl font-bold tracking-wide text-white">{{ config('app.name') }}</span>
            </a>

            <div class="flex items-center gap-4">
                @auth
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 rounded-full bg-brand-accent px-6 py-2.5 font-bold text-white shadow-md transition-all hover:bg-[#e07722]">
                        <i class="fa-solid fa-gauge-high"></i> Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="hidden px-5 py-2 font-semibold text-white transition-colors hover:text-brand-gold md:block">Ingresar</a>
                    <a href="{{ route('public.results') }}" class="rounded-full bg-white px-6 py-2 font-bold text-brand-primary shadow-md transition-all hover:bg-slate-100">Resultados</a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- HERO --}}
    <section class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-brand-primary via-[#0560a6] to-brand-secondary px-6 pb-12 pt-24">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-10"></div>

        <div class="reveal active relative z-10 mx-auto max-w-5xl text-center">
            <div class="animate-float mb-8 inline-flex items-center gap-3 rounded-full border border-white/20 bg-white/10 px-5 py-2 text-sm font-semibold tracking-wide text-white shadow-lg backdrop-blur-sm">
                <i class="fa-solid fa-bolt text-brand-gold"></i> Un solo sistema para cualquier deporte
            </div>

            <h1 class="mb-6 font-display text-5xl font-black leading-[1.1] tracking-tighter text-white drop-shadow-lg md:text-7xl lg:text-8xl">
                Organiza tu torneo <br class="hidden md:block">
                <span class="text-brand-gold">como un pro</span>
            </h1>

            <p class="mx-auto mb-10 max-w-2xl text-lg font-light leading-relaxed text-blue-100 md:text-xl">
                Enfrentamientos, <strong class="font-bold text-white">pruebas individuales con ranking</strong> y <strong class="font-bold text-white">postas</strong>:
                fútbol, vóley, ajedrez, atletismo y más. Equipos, planteles, resultados, tablas y reportes desde una sola plataforma.
            </p>

            <div class="flex flex-col items-center justify-center gap-5 sm:flex-row">
                @auth
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-full bg-brand-accent px-8 py-4 text-lg font-bold text-white shadow-xl transition-all hover:-translate-y-1 hover:bg-[#e07722]">
                        <i class="fa-solid fa-rocket"></i> Ir al Dashboard
                    </a>
                @else
                    {{-- Sin "Comenzar gratis"/"Crear cuenta": las cuentas las crea solo el
                         admin (regla 3 de CLAUDE.md) y el registro publico esta desactivado. --}}
                    <a href="{{ route('public.results') }}" class="flex items-center gap-3 rounded-full bg-brand-accent px-8 py-4 text-lg font-bold text-white shadow-[0_8px_20px_rgba(255,138,44,0.4)] transition-all hover:-translate-y-1 hover:bg-[#e07722]">
                        <i class="fa-solid fa-table-list"></i> Ver resultados
                    </a>
                    <a href="{{ route('login') }}" class="flex items-center gap-3 rounded-full border border-white/30 bg-white/10 px-8 py-4 text-lg font-semibold text-white backdrop-blur-sm transition-all hover:-translate-y-1 hover:bg-white hover:text-brand-primary">
                        <i class="fa-solid fa-right-to-bracket"></i> Ingresar
                    </a>
                @endauth
            </div>
        </div>
    </section>

    {{-- MARQUEE MULTIDEPORTE --}}
    <div class="relative z-10 w-full overflow-hidden border-b border-slate-200 bg-slate-50 py-4 shadow-sm" aria-hidden="true">
        <div class="animate-marquee items-center">
            @for ($i = 0; $i < 4; $i++)
                <div class="mx-6 flex items-center gap-12">
                    <span class="flex items-center gap-3 font-display text-sm font-bold uppercase tracking-widest text-brand-primary">
                        <i class="fa-solid fa-medal text-brand-accent"></i> Multideporte real
                    </span>
                    <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                    <span class="flex items-center gap-3 font-display text-sm font-bold uppercase tracking-widest text-slate-600">
                        <i class="fa-solid fa-trophy text-brand-gold"></i> Torneos y temporadas
                    </span>
                    <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                    <span class="flex items-center gap-3 font-display text-sm font-bold uppercase tracking-widest text-brand-primary">
                        <i class="fa-solid fa-bolt text-brand-primary"></i> Resultados públicos
                    </span>
                    <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                    <span class="flex items-center gap-3 font-display text-sm font-bold uppercase tracking-widest text-slate-600">
                        <i class="fa-solid fa-chart-line text-brand-primary"></i> Tablas y medallero automáticos
                    </span>
                    <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                    <span class="flex items-center gap-3 font-display text-sm font-bold uppercase tracking-widest text-brand-primary">
                        <i class="fa-solid fa-file-pdf text-red-500"></i> Carnets y reportes PDF
                    </span>
                    <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                </div>
            @endfor
        </div>
    </div>

    {{-- FUNCIONALIDADES --}}
    <section id="features" class="relative z-10 mx-auto max-w-7xl bg-white px-6 py-24">
        <div class="reveal mb-20 text-center">
            <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-brand-primary/10 px-4 py-1.5 font-display text-sm font-bold uppercase tracking-widest text-brand-primary">
                <i class="fa-solid fa-star"></i> Funcionalidades
            </div>
            <h2 class="mb-6 font-display text-4xl font-black text-slate-900 md:text-5xl">
                Todo lo que necesitas <br class="hidden md:block">
                <span class="text-brand-primary">en un solo lugar</span>
            </h2>
            <p class="mx-auto max-w-2xl text-lg font-light text-slate-500">
                Desde la creación del torneo hasta el reporte final, con un solo modelo para todos los deportes: nada de sistemas separados por disciplina.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
            <div class="modern-card reveal rounded-3xl p-8">
                <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-gold/10 text-brand-gold">
                    <i class="fa-solid fa-medal text-3xl"></i>
                </div>
                <h3 class="mb-3 font-display text-xl font-bold text-slate-900">Cualquier deporte</h3>
                <p class="text-sm leading-relaxed text-slate-600">Tres formatos: enfrentamiento (fútbol, vóley, ajedrez), individual con ranking (atletismo) y postas por equipos. Cada disciplina decide el suyo.</p>
            </div>
            <div class="modern-card reveal delay-100 rounded-3xl p-8">
                <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-primary/10 text-brand-primary">
                    <i class="fa-solid fa-shield-halved text-3xl"></i>
                </div>
                <h3 class="mb-3 font-display text-xl font-bold text-slate-900">Equipos e inscripciones</h3>
                <p class="text-sm leading-relaxed text-slate-600">Catálogo de equipos con escudo y delegado. Cada temporada inscribe a sus equipos con categoría y, si hace falta, un delegado propio.</p>
            </div>
            <div class="modern-card reveal delay-200 rounded-3xl p-8">
                <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-secondary/10 text-brand-secondary">
                    <i class="fa-solid fa-user-group text-3xl"></i>
                </div>
                <h3 class="mb-3 font-display text-xl font-bold text-slate-900">Planteles por temporada</h3>
                <p class="text-sm leading-relaxed text-slate-600">Jugadores con documento y foto. Dorsal, posición, capitán y estado se definen en cada temporada, con control de edad por categoría.</p>
            </div>
            <div class="modern-card reveal rounded-3xl p-8">
                <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-accent/10 text-brand-accent">
                    <i class="fa-solid fa-calendar-days text-3xl"></i>
                </div>
                <h3 class="mb-3 font-display text-xl font-bold text-slate-900">Jornadas y partidos</h3>
                <p class="text-sm leading-relaxed text-slate-600">Programa jornadas, asigna sedes con ubicación en el mapa, parciales por set o tiempo y relevos de postas.</p>
            </div>
            <div class="modern-card reveal delay-100 rounded-3xl p-8">
                <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-500">
                    <i class="fa-solid fa-chart-column text-3xl"></i>
                </div>
                <h3 class="mb-3 font-display text-xl font-bold text-slate-900">Tablas y medallero</h3>
                <p class="text-sm leading-relaxed text-slate-600">La tabla de posiciones y el medallero se recalculan al cerrar cada resultado. Diferencia de goles o de sets según el deporte.</p>
            </div>
            <div class="modern-card reveal delay-200 rounded-3xl p-8">
                <div class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-red-500/10 text-red-500">
                    <i class="fa-solid fa-square text-3xl"></i>
                </div>
                <h3 class="mb-3 font-display text-xl font-bold text-slate-900">Incidencias y sanciones</h3>
                <p class="text-sm leading-relaxed text-slate-600">Goles, tarjetas y otras incidencias por deporte. Las rojas generan la sanción automáticamente y la reapertura de un resultado queda auditada.</p>
            </div>
        </div>
    </section>

    {{-- DEMO INTERACTIVO: enfrentamiento (Fútbol) vs individual con ranking (Atletismo) --}}
    <section id="demo" class="relative z-10 overflow-hidden border-y border-slate-200 bg-slate-50 px-6 py-24">
        <div class="mx-auto max-w-4xl">
            <div class="reveal mb-10 text-center">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-red-100 bg-red-50 px-4 py-1.5 font-display text-sm font-bold uppercase tracking-widest text-red-500">
                    <i class="fa-solid fa-tv animate-pulse"></i> Experiencia en vivo
                </div>
                <h2 class="font-display text-4xl font-black text-slate-900 md:text-5xl">
                    Interactúa con el <span class="text-brand-primary">Marcador</span>
                </h2>
                <p class="mt-4 font-light text-slate-500">Cambia de deporte y mira cómo el mismo sistema muestra un enfrentamiento o un ranking.</p>
            </div>

            <div class="reveal group relative rounded-[2.5rem] border border-slate-200 bg-white p-8 shadow-2xl md:p-12">
                <div class="relative z-20 mb-10 flex justify-center">
                    <div class="inline-flex rounded-full bg-slate-100 p-1.5 shadow-inner" role="tablist">
                        <button id="btn-football" type="button" data-demo="football" class="relative rounded-full bg-brand-primary px-6 py-2.5 text-sm font-bold text-white shadow-md transition-all duration-300">
                            <i class="fa-solid fa-futbol mr-2"></i>Fútbol
                        </button>
                        <button id="btn-athletics" type="button" data-demo="athletics" class="relative rounded-full px-6 py-2.5 text-sm font-bold text-slate-500 transition-all duration-300 hover:text-slate-800">
                            <i class="fa-solid fa-person-running mr-2"></i>Atletismo
                        </button>
                    </div>
                </div>

                <div id="demo-football" class="fade-enter fade-enter-active">
                    <div class="relative z-10 mb-8 text-center">
                        <h4 class="mb-2 font-display text-sm font-bold uppercase tracking-widest text-brand-primary">Liga Regional 2026 · Enfrentamiento</h4>
                        <span class="inline-flex animate-pulse items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-bold uppercase tracking-widest text-red-600">
                            <span class="h-2 w-2 rounded-full bg-red-500"></span> 2do tiempo
                        </span>
                    </div>

                    <div class="relative z-10 mb-10 flex flex-col items-center justify-center gap-8 md:flex-row md:gap-16">
                        <div class="w-32 text-center">
                            <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full border-4 border-slate-100 bg-slate-50 shadow-md md:h-24 md:w-24">
                                <i class="fa-solid fa-shield-cat text-4xl text-brand-primary"></i>
                            </div>
                            <h4 class="font-display text-base font-bold text-slate-900 md:text-lg">LOS TIGRES</h4>
                        </div>
                        <div class="text-center">
                            <div class="font-display text-6xl font-black tracking-tighter text-brand-primary md:text-8xl">
                                3 <span class="mx-2 align-middle text-4xl text-slate-300">-</span> 2
                            </div>
                        </div>
                        <div class="w-32 text-center">
                            <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full border-4 border-slate-100 bg-slate-50 shadow-md md:h-24 md:w-24">
                                <i class="fa-solid fa-shield-dog text-4xl text-slate-800"></i>
                            </div>
                            <h4 class="font-display text-base font-bold text-slate-900 md:text-lg">REAL ANDINO</h4>
                        </div>
                    </div>

                    <div class="relative z-10 mb-12 flex flex-wrap justify-center gap-3">
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-1.5 text-xs font-bold text-slate-700 shadow-sm"><i class="fa-solid fa-futbol mr-1 text-green-600"></i> Gol 15'</span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-1.5 text-xs font-bold text-slate-700 shadow-sm"><i class="fa-solid fa-futbol mr-1 text-green-600"></i> Gol 32'</span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-1.5 text-xs font-bold text-slate-700 shadow-sm"><i class="fa-solid fa-square mr-1 text-brand-gold"></i> Amarilla 41'</span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-4 py-1.5 text-xs font-bold text-slate-700 shadow-sm"><i class="fa-solid fa-square mr-1 text-red-500"></i> Roja 67'</span>
                    </div>
                </div>

                <div id="demo-athletics" class="fade-enter hidden">
                    <div class="relative z-10 mb-8 text-center">
                        <h4 class="mb-2 font-display text-sm font-bold uppercase tracking-widest text-brand-accent">Juegos Escolares · 100 m planos</h4>
                        <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-widest text-emerald-600">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Individual con ranking
                        </span>
                    </div>

                    <div class="relative z-10 mx-auto mb-12 max-w-md space-y-3">
                        @foreach ([['1', 'bg-amber-400 text-white', 'María Quispe', 'Colegio San Martín', '12.41 s'], ['2', 'bg-slate-300 text-slate-700', 'Lucía Mamani', 'IE Glorioso', '12.58 s'], ['3', 'bg-amber-700 text-white', 'Ana Condori', 'Colegio Santa Rosa', '12.77 s'], ['4', 'bg-slate-100 text-slate-500', 'Rosa Apaza', 'IE Independencia', '13.02 s']] as [$pos, $badge, $athlete, $club, $mark])
                            <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full font-black {{ $badge }}">{{ $pos }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-bold text-slate-800">{{ $athlete }}</p>
                                    <p class="text-xs text-slate-500">{{ $club }}</p>
                                </div>
                                <span class="font-display text-lg font-black text-brand-accent">{{ $mark }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="relative z-10 border-t border-slate-100 pt-8 text-center">
                    <a href="{{ route('public.results') }}" class="group inline-flex items-center gap-3 rounded-full bg-slate-900 px-8 py-4 font-bold text-white shadow-lg shadow-slate-900/30 transition-all hover:bg-black">
                        <i class="fa-solid fa-table-list"></i> Visitar la Zona Pública
                        <i class="fa-solid fa-arrow-right transition-transform group-hover:translate-x-1"></i>
                    </a>
                    <p class="mt-4 text-xs text-slate-500"><i class="fa-solid fa-unlock text-green-500"></i> Jugadores y aficionados no necesitan cuenta para ver resultados</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ROLES DEL SISTEMA (reglas de "Flujo de permisos" de CLAUDE.md) --}}
    <section id="roles" class="relative z-10 bg-white px-6 py-24">
        <div class="mx-auto max-w-7xl">
            <div class="reveal mb-16 text-center">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-brand-primary/10 px-4 py-1.5 font-display text-sm font-bold uppercase tracking-widest text-brand-primary">
                    <i class="fa-solid fa-sitemap"></i> Roles del sistema
                </div>
                <h2 class="mb-4 font-display text-4xl font-black text-slate-900 md:text-5xl">
                    Cada uno con <span class="text-brand-primary">su misión</span>
                </h2>
                <p class="text-lg text-slate-500">Tres perfiles con permisos claros: cada uno ve y gestiona solo lo que le corresponde.</p>
            </div>

            <div class="mx-auto flex max-w-4xl flex-col gap-6">
                <div class="modern-card reveal group flex flex-col items-center gap-8 rounded-3xl p-6 md:flex-row md:items-start md:p-10">
                    <div class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-full bg-brand-gold/10 shadow-sm transition-transform group-hover:scale-110">
                        <i class="fa-solid fa-crown text-3xl text-brand-gold"></i>
                    </div>
                    <div class="text-center md:text-left">
                        <h3 class="mb-2 font-display text-2xl font-bold text-slate-900">Administrador</h3>
                        <p class="mb-4 text-slate-600">Crea los torneos y les asigna organizador, crea las cuentas de usuario y mantiene los catálogos (deportes, disciplinas, roles). Tiene acceso a todos los módulos.</p>
                        <div class="flex flex-wrap justify-center gap-2 md:justify-start">
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm"><i class="fa-solid fa-check mr-1 text-brand-primary"></i> Crear torneos</span>
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm"><i class="fa-solid fa-check mr-1 text-brand-primary"></i> Asignar organizador</span>
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm"><i class="fa-solid fa-check mr-1 text-brand-primary"></i> Crear cuentas</span>
                        </div>
                    </div>
                </div>

                <div class="modern-card reveal delay-100 group flex flex-col items-center gap-8 rounded-3xl p-6 md:flex-row md:items-start md:p-10">
                    <div class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-full bg-brand-secondary/10 shadow-sm transition-transform group-hover:scale-110">
                        <i class="fa-solid fa-clipboard-list text-3xl text-brand-secondary"></i>
                    </div>
                    <div class="text-center md:text-left">
                        <h3 class="mb-2 font-display text-2xl font-bold text-slate-900">Organizador</h3>
                        <p class="mb-4 text-slate-600">Gestiona los torneos que le asignaron: crea sus temporadas, registra los equipos con su delegado, inscribe equipos, programa partidos, carga resultados y genera los reportes.</p>
                        <div class="flex flex-wrap justify-center gap-2 md:justify-start">
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm"><i class="fa-solid fa-check mr-1 text-brand-primary"></i> Temporadas</span>
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm"><i class="fa-solid fa-check mr-1 text-brand-primary"></i> Equipos e inscripciones</span>
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm"><i class="fa-solid fa-check mr-1 text-brand-primary"></i> Resultados</span>
                        </div>
                    </div>
                </div>

                <div class="modern-card reveal delay-200 group flex flex-col items-center gap-8 rounded-3xl p-6 md:flex-row md:items-start md:p-10">
                    <div class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-full bg-brand-primary/10 shadow-sm transition-transform group-hover:scale-110">
                        <i class="fa-solid fa-id-card text-3xl text-brand-primary"></i>
                    </div>
                    <div class="text-center md:text-left">
                        <h3 class="mb-2 font-display text-2xl font-bold text-slate-900">Delegado</h3>
                        <p class="mb-4 text-slate-600">El representante de su equipo. No crea equipos ni inscripciones: arma el plantel de los equipos que tiene a cargo en cada temporada y genera los carnets y fichas de sus jugadores.</p>
                        <div class="flex flex-wrap justify-center gap-2 md:justify-start">
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm"><i class="fa-solid fa-check mr-1 text-brand-primary"></i> Inscribir jugadores</span>
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm"><i class="fa-solid fa-check mr-1 text-brand-primary"></i> Carnets y fichas</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ESTADÍSTICAS (cifras reales: PublicResultsService::landingStats) --}}
    <section id="stats" class="relative z-10 overflow-hidden bg-brand-primary px-6 py-20">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'20\' height=\'20\' viewBox=\'0 0 20 20\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\' fill-rule=\'evenodd\'%3E%3Ccircle cx=\'3\' cy=\'3\' r=\'3\'/%3E%3Cg%3E%3C/svg%3E')] opacity-10"></div>

        <div class="relative z-10 mx-auto grid max-w-6xl grid-cols-2 gap-8 md:grid-cols-4">
            @foreach ([['tournaments', 'Torneos'], ['teams', 'Equipos'], ['players', 'Jugadores'], ['matches', 'Partidos jugados']] as $i => [$key, $label])
                <div class="reveal {{ $i ? 'delay-'.($i * 100) : '' }} text-center">
                    <div class="stat-num mb-2 font-display text-5xl font-black text-white drop-shadow-md md:text-6xl" data-target="{{ $stats[$key] }}">{{ number_format($stats[$key]) }}</div>
                    <div class="text-xs font-bold uppercase tracking-widest text-white/80 md:text-sm">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- REPORTES --}}
    <section id="reports" class="relative z-10 border-b border-slate-200 bg-white px-6 py-24">
        <div class="mx-auto max-w-5xl">
            <div class="reveal mb-16 text-center">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-red-50 px-4 py-1.5 font-display text-sm font-bold uppercase tracking-widest text-red-600">
                    <i class="fa-solid fa-file-pdf"></i> Reportes automáticos
                </div>
                <h2 class="mb-4 font-display text-4xl font-black text-slate-900 md:text-5xl">Documentos <span class="text-red-500">profesionales</span></h2>
                <p class="text-lg text-slate-500">Listos para imprimir con un clic, con los datos de cada temporada.</p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="modern-card reveal group flex items-start gap-6 rounded-3xl p-6">
                    <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 text-2xl text-red-500 transition-transform group-hover:scale-110"><i class="fa-solid fa-id-card"></i></div>
                    <div>
                        <h4 class="mb-2 font-display font-bold text-slate-900">Carnet con QR</h4>
                        <p class="text-sm text-slate-600">Fotocheck por jugador y temporada, individual o masivo. El QR abre una ficha pública para que el árbitro valide la identidad en la cancha.</p>
                    </div>
                </div>
                <div class="modern-card reveal delay-100 group flex items-start gap-6 rounded-3xl p-6">
                    <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 text-2xl text-red-500 transition-transform group-hover:scale-110"><i class="fa-solid fa-list-ol"></i></div>
                    <div>
                        <h4 class="mb-2 font-display font-bold text-slate-900">Padrón de jugadores</h4>
                        <p class="text-sm text-slate-600">El plantel de cada equipo inscrito, ordenado por dorsal, con firmas del delegado y del organizador.</p>
                    </div>
                </div>
                <div class="modern-card reveal group flex items-start gap-6 rounded-3xl p-6">
                    <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 text-2xl text-red-500 transition-transform group-hover:scale-110"><i class="fa-solid fa-chart-pie"></i></div>
                    <div>
                        <h4 class="mb-2 font-display font-bold text-slate-900">Reporte gerencial</h4>
                        <p class="text-sm text-slate-600">Todo en un PDF apaisado: última jornada, posiciones, programación, goleadores y sanciones pendientes.</p>
                    </div>
                </div>
                <div class="modern-card reveal delay-100 group flex items-start gap-6 rounded-3xl p-6">
                    <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 text-2xl text-red-500 transition-transform group-hover:scale-110"><i class="fa-solid fa-file-signature"></i></div>
                    <div>
                        <h4 class="mb-2 font-display font-bold text-slate-900">Ficha de inscripción</h4>
                        <p class="text-sm text-slate-600">Documento oficial por jugador con compromiso firmado, garantía del delegado y registro del organizador.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA FINAL --}}
    <section class="relative z-10 overflow-hidden bg-slate-50 px-6 py-24 text-center">
        <div class="reveal relative z-10 mx-auto max-w-3xl">
            <h2 class="mb-6 font-display text-5xl font-black text-slate-900 md:text-6xl">
                ¿Listo para el <span class="text-brand-primary">pitazo inicial?</span>
            </h2>
            <p class="mb-10 text-xl font-light text-slate-600">
                Sigue los resultados de tu torneo o ingresa al panel para gestionarlo.
            </p>

            <div class="flex flex-col justify-center gap-4 sm:flex-row">
                @auth
                    <a href="{{ route('dashboard') }}" class="flex items-center justify-center gap-3 rounded-full bg-brand-primary px-10 py-5 text-lg font-bold text-white shadow-lg shadow-brand-primary/30 transition-all hover:-translate-y-1 hover:bg-[#034475]">
                        <i class="fa-solid fa-gauge-high"></i> Ir al Dashboard
                    </a>
                @else
                    <a href="{{ route('public.results') }}" class="flex items-center justify-center gap-3 rounded-full bg-brand-primary px-10 py-5 text-lg font-bold text-white shadow-lg shadow-brand-primary/30 transition-all hover:-translate-y-1 hover:bg-[#034475]">
                        <i class="fa-solid fa-table-list"></i> Ver resultados
                    </a>
                    <a href="{{ route('login') }}" class="flex items-center justify-center gap-3 rounded-full border border-slate-300 bg-white px-10 py-5 text-lg font-bold text-slate-700 transition-all hover:-translate-y-1 hover:border-brand-primary hover:text-brand-primary">
                        <i class="fa-solid fa-right-to-bracket"></i> Ingresar
                    </a>
                @endauth
            </div>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer class="relative z-10 border-t border-brand-primary/20 bg-brand-primary px-6 py-10 text-white">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 text-center md:flex-row md:text-left">
            <div>
                <div class="mb-2 font-display text-xl font-bold text-white">
                    <i class="fa-solid fa-trophy text-brand-gold"></i> {{ config('app.name') }}
                </div>
                <p class="text-sm text-blue-100">&copy; {{ date('Y') }} Sistema de Gestión Deportiva Multideporte.</p>
            </div>
            <a href="{{ route('public.results') }}" class="text-sm font-semibold text-white transition-colors hover:text-brand-gold">
                <i class="fa-solid fa-table-list mr-1"></i> Resultados públicos
            </a>
        </div>
    </footer>

    {{-- Interacciones: sin onclick en linea ni funciones globales. --}}
    <script>
        (() => {
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // Aparicion al hacer scroll
            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
            document.querySelectorAll('.reveal').forEach((el) => revealObserver.observe(el));

            // Contador animado (cifras reales; sin "+" porque no son redondeos)
            const stats = document.getElementById('stats');
            const nums = document.querySelectorAll('.stat-num');
            if (stats && ! reduceMotion) {
                nums.forEach((n) => { n.textContent = '0'; });
                let started = false;
                new IntersectionObserver((entries) => {
                    if (started || ! entries.some((e) => e.isIntersecting)) return;
                    started = true;
                    nums.forEach((num) => {
                        const target = parseInt(num.dataset.target, 10) || 0;
                        const start = performance.now();
                        const step = (now) => {
                            const t = Math.min(1, (now - start) / 2500);
                            num.textContent = Math.floor(target * t).toLocaleString('es-PE');
                            if (t < 1) requestAnimationFrame(step);
                        };
                        requestAnimationFrame(step);
                    });
                }, { threshold: 0.5 }).observe(stats);
            }

            // Demo: Futbol (enfrentamiento) / Atletismo (individual con ranking)
            const demos = {
                football: { btn: document.getElementById('btn-football'), panel: document.getElementById('demo-football'), active: 'bg-brand-primary text-white shadow-md scale-105' },
                athletics: { btn: document.getElementById('btn-athletics'), panel: document.getElementById('demo-athletics'), active: 'bg-brand-accent text-white shadow-md shadow-brand-accent/30 scale-105' },
            };
            const base = 'relative px-6 py-2.5 rounded-full text-sm font-bold transition-all duration-300 ';
            const idle = 'text-slate-500 hover:text-slate-800 scale-100';

            document.querySelectorAll('[data-demo]').forEach((button) => {
                button.addEventListener('click', () => {
                    const next = button.dataset.demo;
                    Object.entries(demos).forEach(([key, demo]) => {
                        demo.btn.className = base + (key === next ? demo.active : idle);
                        if (key !== next) {
                            demo.panel.classList.remove('fade-enter-active');
                            setTimeout(() => demo.panel.classList.add('hidden'), 300);
                        }
                    });
                    setTimeout(() => {
                        demos[next].panel.classList.remove('hidden');
                        setTimeout(() => demos[next].panel.classList.add('fade-enter-active'), 50);
                    }, 300);
                });
            });
        })();
    </script>
</body>
</html>
