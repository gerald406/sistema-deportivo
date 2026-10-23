{{-- Pestañas de la pagina publica, una por tarea. variant="top": dentro de la
     cabecera azul, desde sm. variant="bottom": barra fija abajo, solo en movil.
     Programacion y Resultados llevan cuantos tienen; Podios, "definidos de total". --}}
@props(['tabs', 'active', 'counts', 'totals', 'variant' => 'top'])

@php
    $icons = [
        'programacion' => 'fa-regular fa-calendar',
        'resultados' => 'fa-solid fa-list-ol',
        'podios' => 'fa-solid fa-trophy',
        'series' => 'fa-solid fa-table-cells-large',
    ];
    $countOf = fn (string $key) => match ($key) {
        'podios' => $totals['podiumStages'] > 0 ? "{$totals['podiums']} de {$totals['podiumStages']}" : null,
        'series' => null,
        default => $counts[$key] ?? null,
    };
@endphp

@if ($variant === 'bottom')
    <nav aria-label="Secciones" class="fixed inset-x-0 bottom-0 z-30 grid border-t border-[color:var(--rule)] bg-white sm:hidden"
         style="grid-template-columns: repeat({{ count($tabs) }}, minmax(0, 1fr))">
        @foreach ($tabs as $key => $label)
            @php $current = $key === $active; @endphp
            <button type="button" wire:click="setView('{{ $key }}')" wire:key="bottom-tab-{{ $key }}"
                    x-on:click="window.scrollTo({ top: 0 })"
                    @if ($current) aria-current="page" @endif
                    class="flex h-16 flex-col items-center justify-center gap-1 border-t-[3px] text-xs
                           {{ $current ? 'border-[color:var(--signal)] font-extrabold text-[color:var(--lake)]' : 'border-transparent font-semibold text-[color:var(--muted)]' }}">
                <i class="{{ $icons[$key] }} text-lg" aria-hidden="true"></i>
                {{ $label }}
            </button>
        @endforeach
    </nav>
@else
    <nav aria-label="Secciones" class="-mx-4 mt-5 hidden gap-8 overflow-x-auto px-4 sm:flex">
        @foreach ($tabs as $key => $label)
            @php
                $current = $key === $active;
                $count = $countOf($key);
            @endphp
            <button type="button" wire:click="setView('{{ $key }}')" wire:key="top-tab-{{ $key }}"
                    @if ($current) aria-current="page" @endif
                    class="narrow -mb-px flex shrink-0 items-baseline gap-2 border-b-4 pb-3 text-xl font-extrabold
                           {{ $current ? 'border-[color:var(--signal)] text-white' : 'border-transparent text-white/70 hover:text-white' }}">
                {{ $label }}
                @if ($count !== null)
                    <span class="figures text-sm font-bold {{ $current ? 'text-[#ffd2ad]' : '' }}">{{ $count }}</span>
                @endif
            </button>
        @endforeach
    </nav>
@endif
