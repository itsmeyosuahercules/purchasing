@props([
    'column' => null,
    'align' => 'left',
    'sortable' => true,
])

@php
    $alignClass = $align === 'right' ? 'text-right' : 'text-left';
    $canSort = $sortable && $column;
    $current = request('sort');
    $direction = request('direction') === 'asc' ? 'asc' : 'desc';
    $isActive = $canSort && $current === $column;
    $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
    $url = $canSort ? request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection, 'page' => 1]) : null;
@endphp

<th class="px-5 py-3 {{ $alignClass }} font-medium whitespace-nowrap">
    @if($canSort)
        <a href="{{ $url }}" class="inline-flex items-center gap-1 hover:text-slate-700 {{ $isActive ? 'text-slate-700' : '' }}">
            {{ $slot }}
            @if($isActive)
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    @if($direction === 'asc')
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    @endif
                </svg>
            @else
                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9 12 5.25 15.75 9m0 6L12 18.75 8.25 15" />
                </svg>
            @endif
        </a>
    @else
        {{ $slot }}
    @endif
</th>
