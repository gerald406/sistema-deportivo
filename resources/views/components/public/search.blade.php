{{-- Buscador de la pagina publica con sugerencias agrupadas: delegaciones,
     competencias y participantes. Elegir una aplica su filtro; Enter (o
     "Buscar en...") busca el texto en la pestaña. Flechas arriba y abajo
     recorren las sugerencias; Escape las cierra.
     $suggestions sale de PublicResults (null = nada que sugerir). --}}
@props(['suggestions' => null, 'inputId', 'panelClass' => 'right-0 w-[28rem]'])

@php
    $tabLabels = ['programacion' => 'Programación', 'resultados' => 'Resultados', 'podios' => 'Podios'];
    $option = 'flex min-h-11 w-full items-center gap-3 px-4 py-2 text-left hover:bg-[color:var(--wash)] focus:bg-[color:var(--wash)] focus:outline-none';
    $empty = $suggestions && $suggestions['teams']->isEmpty() && $suggestions['seasons']->isEmpty() && $suggestions['players']->isEmpty();
@endphp

<div x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" {{ $attributes->class('relative') }}>
    <form wire:submit="applySearch" x-on:submit="open = false" role="search">
        <label for="{{ $inputId }}" class="sr-only">Buscar delegación, competencia o participante</label>
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[color:var(--muted)]" aria-hidden="true"></i>
        <input id="{{ $inputId }}" type="search" wire:model.live.debounce.250ms="query" maxlength="60" autocomplete="off"
               placeholder="Buscar delegación, competencia o participante"
               x-on:focus="open = true" x-on:input="open = true"
               x-on:keydown.down.prevent="open = true; $nextTick(() => $refs.options && $focus.within($refs.options).first())"
               aria-controls="{{ $inputId }}-options" :aria-expanded="open && @js($suggestions !== null)"
               class="h-11 w-full rounded-lg border-0 bg-white pl-9 text-base text-[color:var(--ink)] placeholder:text-[color:var(--muted)] focus:ring-0 sm:text-sm">
    </form>

    @if ($suggestions)
        <div id="{{ $inputId }}-options" x-ref="options" x-show="open" x-cloak x-transition.opacity.duration.100ms
             x-on:keydown.down.prevent="$focus.wrap().next()" x-on:keydown.up.prevent="$focus.wrap().previous()"
             class="absolute z-50 mt-2 max-h-[70vh] overflow-y-auto rounded-xl bg-white text-[color:var(--ink)] shadow-2xl ring-1 ring-[color:var(--rule)] {{ $panelClass }}">

            @if ($suggestions['teams']->isNotEmpty())
                <p class="px-4 pb-1 pt-3 text-xs font-bold uppercase tracking-wider text-[color:var(--muted)]">Delegaciones</p>
                @foreach ($suggestions['teams'] as $team)
                    <div class="flex items-center" wire:key="suggest-team-{{ $team->id }}">
                        <button type="button" wire:click="pickTeam({{ $team->id }})" x-on:click="open = false" class="{{ $option }} min-w-0 flex-1">
                            <i class="fa-solid fa-people-group w-5 text-center text-[color:var(--lake)]" aria-hidden="true"></i>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $team->name }}</span>
                                <span class="block text-xs text-[color:var(--muted)]">Ver sus partidos y podios</span>
                            </span>
                        </button>
                        <button type="button" x-on:click="setMine({{ $team->id }})"
                                :aria-pressed="mine === {{ $team->id }}"
                                class="mr-3 h-9 shrink-0 rounded-full border border-[color:var(--rule)] px-3 text-xs font-bold text-[color:var(--lake)] hover:border-[color:var(--lake)]">
                            <span x-text="mine === {{ $team->id }} ? 'Es la mía ✓' : 'Es la mía'">Es la mía</span>
                        </button>
                    </div>
                @endforeach
            @endif

            @if ($suggestions['seasons']->isNotEmpty())
                <p class="px-4 pb-1 pt-3 text-xs font-bold uppercase tracking-wider text-[color:var(--muted)]">Competencias</p>
                @foreach ($suggestions['seasons'] as $season)
                    <button type="button" wire:key="suggest-season-{{ $season->id }}" wire:click="pickSeason({{ $season->id }})" x-on:click="open = false" class="{{ $option }}">
                        <i class="fa-solid fa-medal w-5 text-center text-[color:var(--lake)]" aria-hidden="true"></i>
                        <span class="min-w-0">
                            <span class="block truncate font-semibold">{{ $season->name }}</span>
                            <span class="block text-xs text-[color:var(--muted)]">{{ $season->sport->name }}</span>
                        </span>
                    </button>
                @endforeach
            @endif

            @if ($suggestions['players']->isNotEmpty())
                <p class="px-4 pb-1 pt-3 text-xs font-bold uppercase tracking-wider text-[color:var(--muted)]">Participantes</p>
                @foreach ($suggestions['players'] as $entry)
                    <button type="button" wire:key="suggest-player-{{ $entry->id }}" wire:click="pickPlayer({{ $entry->id }})" x-on:click="open = false" class="{{ $option }}">
                        <i class="fa-regular fa-user w-5 text-center text-[color:var(--lake)]" aria-hidden="true"></i>
                        <span class="min-w-0">
                            <span class="block truncate font-semibold">{{ $entry->player->last_name }}, {{ $entry->player->first_name }}</span>
                            <span class="block truncate text-xs text-[color:var(--muted)]">{{ $entry->seasonTeam->team->name }}</span>
                        </span>
                    </button>
                @endforeach
            @endif

            @if ($empty)
                <p class="px-4 py-4 text-sm text-[color:var(--muted)]">Ninguna delegación, competencia o participante se llama así.</p>
            @endif

            <div class="mt-2 border-t border-[color:var(--rule)] bg-[color:var(--wash)] px-4 py-3">
                <p class="text-xs font-bold text-[color:var(--muted)]">Buscar «{{ $suggestions['term'] }}» en</p>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    @foreach ($tabLabels as $key => $label)
                        <button type="button" wire:click="applySearch('{{ $key }}')" x-on:click="open = false"
                                class="flex min-h-11 flex-col items-center justify-center rounded-lg border border-[color:var(--rule)] bg-white px-2 py-1 text-xs font-bold hover:border-[color:var(--lake)] focus:border-[color:var(--lake)] focus:outline-none">
                            {{ $label }}
                            <span class="figures font-semibold text-[color:var(--muted)]">{{ $suggestions['counts'][$key] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
