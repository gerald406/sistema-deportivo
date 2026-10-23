{{-- Avance de los podios de lo que se esta viendo: "2 de 6 definidos" con su
     barra. $label nombra el alcance (un deporte, una serie o toda la olimpiada). --}}
@props(['label', 'decided', 'total'])

@if ($total > 0)
    @php $percent = (int) round($decided * 100 / $total); @endphp
    <div {{ $attributes->class('rounded-lg border border-[color:var(--rule)] px-4 py-3 sm:px-5 sm:py-4') }}>
        <div class="flex items-baseline justify-between gap-3">
            <p class="narrow text-lg font-bold sm:text-2xl">{{ $label }}</p>
            <p class="figures shrink-0 text-sm text-[color:var(--muted)]">
                <span class="narrow text-2xl font-extrabold text-[color:var(--ink)]">{{ $decided }}</span> de {{ $total }} definidos
            </p>
        </div>
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-[color:var(--wash)]" role="progressbar"
             aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $decided }}" aria-label="Podios definidos">
            <div class="h-full rounded-full bg-[color:var(--lake)]" style="width: {{ max($percent, $decided > 0 ? 2 : 0) }}%"></div>
        </div>
    </div>
@endif
