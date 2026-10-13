{{-- Un partido de la llave: los dos lados con su marcador. Recibe $match con
     participants cargados via ParticipantLabel::eagerLoad(). --}}
@php($closed = in_array($match->status, [\App\Enums\MatchStatus::Played, \App\Enums\MatchStatus::Walkover], true))
<div class="rounded-lg border border-gray-200 bg-gray-50 text-sm">
    @foreach (['home', 'away'] as $side)
        @php($p = $match->participants->firstWhere('side.value', $side))
        <div @class([
            'flex items-center justify-between gap-2 px-3 py-1.5',
            'border-t border-gray-200' => $side === 'away',
            'font-semibold text-gray-900' => $closed && $p?->position === 1,
            'text-gray-600' => ! ($closed && $p?->position === 1),
        ])>
            <span class="truncate">{{ $p ? \App\Support\ParticipantLabel::for($p) : 'Por definir' }}</span>
            <span class="font-mono">
                @if ($closed && $p)
                    {{ $p->result_value !== null ? (int) $p->result_value : '—' }}@if ($p->tiebreak_score !== null)<span class="text-xs text-gray-400"> ({{ $p->tiebreak_score }})</span>@endif
                @endif
            </span>
        </div>
    @endforeach
    <div class="flex items-center justify-between border-t border-gray-200 px-3 py-1 text-xs text-gray-400">
        <span>{{ $match->scheduled_at?->format('d/m H:i') ?? 'Sin programar' }}</span>
        <span>
            @if ($match->tiebreak_type)
                {{ $match->tiebreak_type->label() }} ·
            @endif
            {{ $match->status->label() }}
        </span>
    </div>
</div>
