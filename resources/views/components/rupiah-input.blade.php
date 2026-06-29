@props([
    'label' => null,
    'name',
    'value' => null,
    'hint' => null,
    'required' => false,
])

@php
    $initial = old($name) !== null
        ? old($name)
        : ($value !== null && $value !== '' ? (int) round((float) $value) : '');
@endphp

<div x-data="rupiah(@js((string) $initial))">
    @if($label)<label for="{{ $name }}_display" class="field-label">{{ $label }}</label>@endif
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">Rp</span>
        <input
            id="{{ $name }}_display"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            x-bind:value="display"
            x-on:input="onInput($event)"
            @if($required) required @endif
            {{ $attributes->class('field-input pl-9') }}
            placeholder="0"
        >
        <input type="hidden" name="{{ $name }}" x-bind:value="raw">
    </div>
    @if($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
