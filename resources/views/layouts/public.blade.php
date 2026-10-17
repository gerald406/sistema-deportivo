<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Programación, resultados y podios por serie de {{ config('app.name') }}.">
    <title>{{ $title ?? config('app.name') }}</title>

    {{-- Archivo (variable, con eje de ancho): una sola familia. Angosta para
         horas, marcadores y titulos de dia, como un tablero deportivo; ancho
         normal para el texto. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap" rel="stylesheet">

    {{-- Tailwind compilado; Font Awesome viene dentro de app.css. --}}
    @vite(['resources/css/app.css'])

    <style>
        :root {
            --ink: #0c2740;      /* texto y marcadores */
            --lake: #045694;     /* cabecera: el azul de marca */
            --wash: #eef4f9;     /* barras de dia y campos */
            --rule: #d5dfe9;     /* lineas entre partidos */
            --muted: #5b7186;    /* datos secundarios */
            --signal: #ff8a2c;   /* hoy y ganador: lo unico en naranja */
            --gold: #f2c14e;     /* puestos del podio */
            --silver: #c9d3dc;
            --bronze: #96562b;
        }

        body { font-family: 'Archivo', ui-sans-serif, system-ui, sans-serif; color: var(--ink); background: #fff; }
        .narrow { font-stretch: 72%; }
        .figures { font-variant-numeric: tabular-nums; }
        [x-cloak] { display: none !important; }

        /* El titulo de cada dia se queda arriba mientras se recorre su lista. */
        .day { scroll-margin-top: 0.5rem; }

        /* Tarjeta de serie: el borde y la letra responden al puntero, nada mas. */
        .series-card { transition: border-color .15s ease; }
        .series-card:hover { border-color: var(--lake); }
        .series-card .series-mark { transition: filter .15s ease; }
        .series-card:hover .series-mark { filter: brightness(.88); }

        a:focus-visible, button:focus-visible, select:focus-visible, input:focus-visible {
            outline: 3px solid var(--signal);
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; scroll-behavior: auto !important; }
        }
    </style>

    @livewireStyles
</head>
<body class="flex min-h-screen flex-col antialiased">
    {{ $slot }}

    <footer class="mt-auto border-t border-[color:var(--rule)] px-4 py-6 text-sm text-[color:var(--muted)]">
        <div class="mx-auto flex max-w-5xl flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ config('app.name') }}, {{ date('Y') }}</span>
            <span>Los horarios pueden cambiar. Confirma con tu delegado antes de cada partido.</span>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
