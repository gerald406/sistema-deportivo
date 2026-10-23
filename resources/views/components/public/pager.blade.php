{{-- Paginador de la pagina publica: "21–40 de 87" y paginas numeradas (en
     movil, "Anterior", "Página 2 de 5" y "Siguiente", mas faciles de tocar).
     Al cambiar de pagina se vuelve al inicio del listado (#listado). --}}
@props(['paginator', 'one', 'many', 'pageName' => 'page'])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $pages = collect([1, $current - 1, $current, $current + 1, $last])
        ->filter(fn (int $p) => $p >= 1 && $p <= $last)
        ->unique()->sort()->values();
    $toTop = "document.getElementById('listado')?.scrollIntoView()";
    $button = 'figures inline-flex h-9 min-w-9 items-center justify-center rounded-md px-3 text-sm font-semibold';
@endphp

@if ($paginator->total() > 0)
    <nav class="flex flex-col-reverse items-start gap-3 pt-6 sm:flex-row sm:items-center sm:justify-between" aria-label="Páginas">
        <p class="figures text-sm text-[color:var(--muted)]">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
            {{ $paginator->total() === 1 ? $one : $many }}
        </p>

        @if ($paginator->hasPages())
            <div class="flex w-full items-center justify-between gap-1 sm:w-auto sm:flex-wrap sm:justify-start">
                <button type="button" wire:click="previousPage('{{ $pageName }}')" x-on:click="{{ $toTop }}"
                        @disabled($paginator->onFirstPage())
                        class="{{ $button }} border border-[color:var(--rule)] hover:border-[color:var(--lake)] hover:text-[color:var(--lake)] disabled:pointer-events-none disabled:opacity-40">
                    <i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i>
                    <span class="ml-2">Anterior</span>
                </button>

                <span class="figures text-sm font-semibold sm:hidden">Página {{ $current }} de {{ $last }}</span>

                @foreach ($pages as $p)
                    @if (! $loop->first && $p - $pages[$loop->index - 1] > 1)
                        <span class="hidden px-1 text-[color:var(--muted)] sm:inline" aria-hidden="true">…</span>
                    @endif
                    <button type="button" wire:click="gotoPage({{ $p }}, '{{ $pageName }}')" x-on:click="{{ $toTop }}"
                            wire:key="page-{{ $p }}"
                            @if ($p === $current) aria-current="page" @endif
                            class="{{ $button }} hidden sm:inline-flex {{ $p === $current ? 'bg-[color:var(--ink)] text-white' : 'hover:bg-[color:var(--wash)]' }}">
                        {{ $p }}
                    </button>
                @endforeach

                <button type="button" wire:click="nextPage('{{ $pageName }}')" x-on:click="{{ $toTop }}"
                        @disabled(! $paginator->hasMorePages())
                        class="{{ $button }} border border-[color:var(--rule)] hover:border-[color:var(--lake)] hover:text-[color:var(--lake)] disabled:pointer-events-none disabled:opacity-40">
                    <span class="mr-2">Siguiente</span>
                    <i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i>
                </button>
            </div>
        @endif
    </nav>
@endif
