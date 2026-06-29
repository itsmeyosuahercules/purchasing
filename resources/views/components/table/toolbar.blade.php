@props([
    'placeholder' => 'Cari...',
    'action' => null,
])

@php
    $hasQuery = request()->hasAny(['search', 'status', 'supplier_id', 'sort']);
@endphp

<form method="GET" action="{{ $action }}" class="flex flex-wrap items-center gap-3 mb-5">
    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
    @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif

    <div class="relative flex-1 min-w-[200px] max-w-sm">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
        </span>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="{{ $placeholder }}"
               x-data x-on:input.debounce.450ms="$el.form.requestSubmit()"
               class="field-input pl-9">
    </div>

    {{ $filters ?? '' }}

    <div class="ml-auto flex items-center gap-2">
        @if($hasQuery)
            <a href="{{ url()->current() }}" class="text-sm text-slate-500 hover:text-slate-800 px-2">Reset</a>
        @endif
        {{ $trailing ?? '' }}
        <select name="per_page" x-data x-on:change="$el.form.requestSubmit()" class="field-input w-auto">
            @foreach(\App\Support\TableQuery::PER_PAGE_OPTIONS as $n)
                <option value="{{ $n }}" @selected((int) request('per_page', 15) === $n)>{{ $n }} / hal</option>
            @endforeach
        </select>
    </div>
</form>
