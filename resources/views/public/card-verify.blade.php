<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Verificación de carnet — {{ config('app.name') }}</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
          crossorigin="anonymous" referrerpolicy="no-referrer">
    @vite(['resources/css/app.css'])

    <style>
        body { background: linear-gradient(135deg, #0f2d5e 0%, #1a5699 50%, #0f2d5e 100%); }
    </style>
</head>
{{-- Pagina publica a la que apunta el QR del carnet (ruta firmada, sin login).
     Diseno de la referencia (pichanga public/player-verify), pero SIN datos
     sensibles: nada de documento completo, fecha de nacimiento ni edad. --}}
<body class="flex min-h-screen items-center justify-center p-4 font-sans antialiased">
    <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl">

        @if ($valid)
            <div class="flex items-center justify-center gap-2 bg-emerald-500 px-4 py-3 text-center text-white">
                <i class="fa-solid fa-circle-check text-lg"></i>
                <span class="text-sm font-bold uppercase tracking-wider">Carnet válido</span>
            </div>
        @else
            <div class="flex items-center justify-center gap-2 bg-red-500 px-4 py-3 text-center text-white">
                <i class="fa-solid fa-circle-exclamation text-lg"></i>
                <span class="text-sm font-bold uppercase tracking-wider">Carnet no vigente</span>
            </div>
        @endif

        <div class="bg-slate-800 px-5 py-4 text-center text-white">
            <p class="mb-1 text-xs font-bold uppercase tracking-widest text-blue-300">
                <i class="fa-solid fa-trophy mr-1"></i> {{ config('app.name') }}
            </p>
            <p class="text-sm font-semibold">{{ $tournament->name }}</p>
            <p class="text-xs text-slate-300">{{ $season->name }}</p>
        </div>

        <div class="flex flex-col items-center px-6 pb-2 pt-5">
            <div @class([
                    'h-28 w-28 overflow-hidden rounded-full border-4 bg-gray-200 shadow-lg',
                    'border-emerald-400' => $valid,
                    'border-red-400' => ! $valid,
                ])>
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" class="h-full w-full object-cover" alt="{{ $player->fullName() }}">
                @else
                    <div class="flex h-full w-full items-center justify-center bg-indigo-100">
                        <i class="fa-solid fa-user text-4xl text-indigo-400"></i>
                    </div>
                @endif
            </div>

            @if ($entry->is_captain)
                <span class="mt-2 rounded-full border border-amber-300 bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700">
                    <i class="fa-solid fa-crown mr-1"></i> Capitán
                </span>
            @endif
        </div>

        <div class="px-6 pb-2 text-center">
            <h1 class="text-2xl font-extrabold leading-tight text-gray-900">{{ $player->fullName() }}</h1>
            <div class="mt-1 flex items-center justify-center gap-2 text-sm text-gray-600">
                @if ($teamLogoUrl)
                    <img src="{{ $teamLogoUrl }}" class="h-5 w-5 rounded-full border border-gray-200 object-cover" alt="{{ $team->name }}">
                @else
                    <i class="fa-solid fa-shield-halved text-gray-400"></i>
                @endif
                <span class="font-medium">{{ $team->name }}</span>
            </div>
        </div>

        <div class="px-6 pb-5 pt-3">
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <i class="fa-solid fa-shirt mr-1 text-indigo-400"></i> Dorsal
                    </p>
                    <p class="font-bold text-gray-800">{{ $entry->shirt_number !== null ? '#'.$entry->shirt_number : '—' }}</p>
                </div>

                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <i class="fa-solid fa-circle-info mr-1 text-indigo-400"></i> Estado
                    </p>
                    <p @class(['font-bold', 'text-emerald-600' => $valid, 'text-red-600' => ! $valid])>
                        <i class="fa-solid fa-circle mr-1 text-xs"></i> {{ $statusLabel }}
                    </p>
                </div>

                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <i class="fa-solid fa-id-card mr-1 text-indigo-400"></i> Documento
                    </p>
                    {{-- Solo los ultimos digitos: sirve para cotejar con el documento fisico. --}}
                    <p class="font-bold tracking-wider text-gray-800">{{ $maskedDocument }}</p>
                </div>

                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        <i class="fa-solid fa-hashtag mr-1 text-indigo-400"></i> N° carnet
                    </p>
                    <p class="text-sm font-bold text-gray-800">{{ $cardNumber }}</p>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 bg-gray-50 px-6 py-3 text-center text-xs text-gray-400">
            Verificado el {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
