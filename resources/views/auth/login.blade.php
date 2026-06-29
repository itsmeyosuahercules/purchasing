<x-guest-layout title="Masuk">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        <div class="lg:hidden flex items-center gap-2 font-semibold text-slate-900 mb-6">
            <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-brand-600 text-white">
                <x-icon name="orders" class="w-5 h-5" />
            </span>
            {{ config('app.name') }}
        </div>

        <h1 class="text-2xl font-bold text-slate-900">Selamat datang</h1>
        <p class="text-slate-500 text-sm mt-1 mb-6">Masuk untuk melanjutkan ke dashboard.</p>

        @if($errors->any())
            <div class="mb-5 flex items-start gap-2 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                <x-icon name="x-circle" class="w-5 h-5 shrink-0 mt-0.5" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <x-input name="username" label="Username" autofocus required autocomplete="username" placeholder="Masukkan username" />

            <div>
                <label for="password" class="field-label">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="field-input" placeholder="Masukkan password">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600 select-none">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Ingat saya
            </label>

            <x-button type="submit" class="w-full">Masuk</x-button>
        </form>
    </div>
</x-guest-layout>
