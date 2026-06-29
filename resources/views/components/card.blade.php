@props(['title' => null, 'subtitle' => null, 'padding' => true])

<div {{ $attributes->class('bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden') }}>
    @if($title || isset($actions))
        <div class="flex items-center justify-between gap-4 px-5 py-4 border-b border-slate-100">
            <div>
                @if($title)<h2 class="font-semibold text-slate-900">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-sm text-slate-500 mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div class="{{ $padding ? 'p-5' : '' }}">
        {{ $slot }}
    </div>
</div>
