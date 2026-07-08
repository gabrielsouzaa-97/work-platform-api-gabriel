@props([
    'options' => [],      // array<string|int, string> value => label
    'model' => '',        // Livewire property name, e.g. 'createStatus'
    'selected' => null,   // current value
    'placeholder' => 'Selecione…',
])

@php
    $selectedLabel = $selected !== null && $selected !== '' && array_key_exists($selected, $options)
        ? $options[$selected]
        : null;
@endphp

<div x-data="{ open: false }" @click.outside="open = false" class="relative">
    <button
        type="button"
        @click="open = !open"
        class="w-full flex items-center justify-between rounded-md border border-outline-variant bg-surface-container-lowest px-3 py-2 text-[13px] text-left outline-none focus:border-primary cursor-pointer"
        :aria-expanded="open"
        aria-haspopup="listbox"
    >
        <span class="{{ $selectedLabel ? 'text-on-surface' : 'text-on-surface-variant' }} truncate">
            {{ $selectedLabel ?? $placeholder }}
        </span>
        <span class="material-symbols-outlined text-on-surface-variant shrink-0" style="font-size:18px">expand_more</span>
    </button>

    <div
        x-show="open"
        x-transition.opacity.duration.100ms
        role="listbox"
        class="absolute z-[60] mt-1 w-full max-h-56 overflow-y-auto rounded-md border border-outline-variant bg-surface-container-high shadow-xl py-1"
        style="display: none;"
    >
        @foreach ($options as $value => $label)
            <button
                type="button"
                role="option"
                wire:click="$set('{{ $model }}', '{{ $value }}')"
                @click="open = false"
                class="w-full text-left px-3 py-2 text-[13px] transition-colors
                       {{ (string) $value === (string) $selected ? 'bg-primary/15 text-primary font-medium' : 'text-on-surface hover:bg-surface-container-highest' }}"
                @if((string) $value === (string) $selected) aria-selected="true" @endif
            >
                {{ $label }}
            </button>
        @endforeach
    </div>
</div>
