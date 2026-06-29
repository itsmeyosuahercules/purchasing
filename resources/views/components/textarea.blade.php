@props([
    'label' => null,
    'name',
    'value' => null,
    'hint' => null,
    'rows' => 5,
])

<div>
    @if($label)<label for="{{ $name }}" class="field-label">{{ $label }}</label>@endif
    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        {{ $attributes->class('field-input font-mono text-sm leading-relaxed') }}
    >{{ old($name, $value) }}</textarea>
    @if($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
