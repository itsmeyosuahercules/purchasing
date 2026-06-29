@if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition
         class="mb-5 flex items-start gap-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3">
        <x-icon name="check" class="w-5 h-5 shrink-0 mt-0.5 text-emerald-600" />
        <p class="text-sm flex-1">{{ session('success') }}</p>
        <button @click="show = false" class="text-emerald-600 hover:text-emerald-800">&times;</button>
    </div>
@endif

@if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-transition
         class="mb-5 flex items-start gap-3 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3">
        <x-icon name="x-circle" class="w-5 h-5 shrink-0 mt-0.5 text-red-600" />
        <p class="text-sm flex-1">{{ session('error') }}</p>
        <button @click="show = false" class="text-red-600 hover:text-red-800">&times;</button>
    </div>
@endif
