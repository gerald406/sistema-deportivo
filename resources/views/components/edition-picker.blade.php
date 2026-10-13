{{-- Selector de olimpiada para las pantallas que usan el trait SelectsEdition.
     Con una sola olimpiada a cargo solo muestra su nombre. --}}
@props(['options'])

@if ($options->count() > 1)
    <div class="flex flex-col gap-2 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 sm:flex-row sm:items-center">
        <label for="edition-picker" class="text-sm font-medium text-gray-700">
            <i class="fa-solid fa-trophy mr-1 text-amber-500"></i> Olimpiada
        </label>
        <select id="edition-picker" wire:model.live="editionId"
                class="flex-1 rounded-lg border-gray-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ($options as $option)
                <option value="{{ $option->id }}">{{ $option->name }}{{ $option->is_active ? '' : ' (inactiva)' }}</option>
            @endforeach
        </select>
    </div>
@elseif ($options->count() === 1)
    <p class="text-sm text-gray-600">
        <i class="fa-solid fa-trophy mr-1 text-amber-500"></i>
        <span class="font-medium text-gray-900">{{ $options->first()->name }}</span>
    </p>
@endif
