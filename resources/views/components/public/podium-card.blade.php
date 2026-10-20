{{-- Podio directo de una fase (1.º, 2.º y 3.º). Si el puesto es de un
     participante, su nombre va primero y la delegacion debajo. --}}
@props(['stage'])

<article {{ $attributes->class('rounded-lg border border-[color:var(--rule)] bg-white p-4') }}>
    <h4 class="font-bold leading-snug">{{ $stage->season->name }}</h4>
    <p class="text-sm text-[color:var(--muted)]">{{ $stage->name }}</p>
    @if ($stage->scheduled_at || $stage->venue)
        <p class="mt-1 text-sm text-[color:var(--muted)]">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            {{ $stage->scheduled_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}{{ $stage->venue ? ' · '.$stage->venue->name : '' }}
        </p>
    @endif

    @if ($stage->results->isEmpty())
        <p class="mt-3 text-sm font-medium">Por disputar</p>
    @endif

    <ol class="mt-3 space-y-2">
        @foreach ($stage->results as $result)
            @php $player = $result->seasonTeamPlayer?->player; @endphp
            <li class="grid grid-cols-[1.75rem_minmax(0,1fr)] items-start gap-3">
                <span @class([
                    'figures mt-0.5 flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold',
                    'bg-[color:var(--gold)] text-[color:var(--ink)]' => $result->place === 1,
                    'bg-[color:var(--silver)] text-[color:var(--ink)]' => $result->place === 2,
                    'bg-[color:var(--bronze)] text-white' => $result->place === 3,
                ])>
                    {{ $result->place }}<span class="sr-only">.º puesto</span>
                </span>
                <span class="min-w-0">
                    <span class="block truncate {{ $result->place === 1 ? 'font-bold' : 'font-medium' }}">
                        {{ $player ? "{$player->last_name}, {$player->first_name}" : ($result->team?->name ?? '—') }}
                    </span>
                    @if ($player && $result->team)
                        <span class="block truncate text-sm text-[color:var(--muted)]">{{ $result->team->name }}</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ol>
</article>
