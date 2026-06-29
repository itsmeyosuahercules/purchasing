@props([
    'label',
    'value',
    'icon' => 'dashboard',
    'tone' => 'brand',
    'href' => null,
])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'green' => 'bg-emerald-50 text-emerald-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
    $tone = $tones[$tone] ?? $tones['brand'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    class="group bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex items-center gap-4 transition hover:shadow-md @if($href) hover:border-brand-300 @endif">
    <span class="flex items-center justify-center w-12 h-12 rounded-xl {{ $tone }}">
        <x-icon :name="$icon" class="w-6 h-6" />
    </span>
    <div>
        <p class="text-sm text-slate-500">{{ $label }}</p>
        <p class="text-2xl font-bold text-slate-900 leading-tight">{{ $value }}</p>
    </div>
</{{ $tag }}>
