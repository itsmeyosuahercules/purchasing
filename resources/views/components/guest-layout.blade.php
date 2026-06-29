@props(['title' => 'Masuk'])

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 antialiased">
    <div class="min-h-full flex">
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-brand-600 via-brand-700 to-indigo-900 relative overflow-hidden">
            <div class="absolute inset-0 opacity-20"
                 style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 32px 32px;"></div>
            <div class="relative flex flex-col justify-between p-12 text-white">
                <div class="flex items-center gap-2 font-semibold text-lg">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-white/15">
                        <x-icon name="orders" class="w-5 h-5" />
                    </span>
                    {{ config('app.name') }}
                </div>
                <div>
                    <h1 class="text-3xl font-bold leading-tight">Sistem Pemesanan Supplier</h1>
                    <p class="mt-3 text-white/70 max-w-sm">Kelola pesanan ke supplier secara aman dengan kontrol akses dan persetujuan terpusat.</p>
                </div>
                <p class="text-sm text-white/50">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
            </div>
        </div>

        <div class="flex-1 flex items-center justify-center p-6">
            <div class="w-full max-w-md">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
