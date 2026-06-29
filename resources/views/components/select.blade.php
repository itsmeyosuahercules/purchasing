@props([
    'label' => null,
    'name',
    'hint' => null,
])

<div>
    @if($label)<label for="{{ $name }}" class="field-label">{{ $label }}</label>@endif
    <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class('field-input') }}>
        {{ $slot }}
    </select>
    @if($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
