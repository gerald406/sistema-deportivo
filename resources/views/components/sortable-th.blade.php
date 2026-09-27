@props(['field', 'label', 'sortField', 'sortDirection'])

<th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
    <button type="button" wire:click="sortBy('{{ $field }}')"
            class="flex items-center gap-1.5 hover:text-gray-700">
        <span>{{ $label }}</span>
        @if ($sortField === $field)
            <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-[10px] text-indigo-600"></i>
        @else
            <i class="fa-solid fa-sort text-[10px] text-gray-300"></i>
        @endif
    </button>
</th>
