@props(['title' => ''])

@php
    $user = auth()->user();

    $nav = $user->isAdmin()
        ? [
            ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard'],
            ['route' => 'admin.orders.index', 'active' => 'admin.orders.*', 'icon' => 'orders', 'label' => 'Pesanan'],
            ['route' => 'admin.suppliers.index', 'active' => 'admin.suppliers.*', 'icon' => 'suppliers', 'label' => 'Supplier'],
            ['route' => 'admin.products.index', 'active' => 'admin.products.*', 'icon' => 'products', 'label' => 'Produk'],
            ['route' => 'admin.users.index', 'active' => 'admin.users.*', 'icon' => 'users', 'label' => 'Pengguna'],
            ['route' => 'admin.settings.edit', 'active' => 'admin.settings.*', 'icon' => 'settings', 'label' => 'Pengaturan'],
        ]
        : [
            ['route' => 'employee.orders.create', 'active' => 'employee.orders.create', 'icon' => 'plus', 'label' => 'Buat Pesanan'],
            ['route' => 'employee.orders.history', 'active' => 'employee.orders.history', 'icon' => 'history', 'label' => 'Riwayat Pesanan'],
        ];
@endphp

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased" x-data="{ sidebar: false }">
    <div class="min-h-full">
        {{-- Mobile overlay --}}
        <div x-show="sidebar" x-cloak @click="sidebar = false"
             x-transition.opacity
             class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 text-slate-300 flex flex-col transition-transform duration-200 lg:translate-x-0"
            :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
            <div class="h-16 flex items-center gap-2 px-5 border-b border-white/10 text-white font-semibold">
                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-brand-600">
                    <x-icon name="orders" class="w-5 h-5" />
                </span>
                <span class="truncate">{{ config('app.name') }}</span>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                @foreach($nav as $item)
                    @php $isActive = request()->routeIs($item['active']); @endphp
                    <a href="{{ route($item['route']) }}"
                       @click="sidebar = false"
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                              {{ $isActive ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <x-icon :name="$item['icon']" class="w-5 h-5 shrink-0" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="p-3 border-t border-white/10">
                <div class="flex items-center gap-3 px-2 py-2">
                    <span class="flex items-center justify-center w-9 h-9 rounded-full bg-brand-600 text-white text-sm font-semibold uppercase">
                        {{ Str::substr($user->name, 0, 1) }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ $user->name }}</p>
                        <p class="text-xs text-slate-400">{{ $user->role->label() }}</p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Main --}}
        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 h-16 bg-white/90 backdrop-blur border-b border-slate-200 flex items-center gap-3 px-4 sm:px-6">
                <button @click="sidebar = true" class="lg:hidden p-2 -ml-2 text-slate-500 hover:text-slate-900">
                    <x-icon name="menu" class="w-6 h-6" />
                </button>
                <h1 class="text-lg font-semibold text-slate-900 truncate">{{ $title }}</h1>

                <div class="ml-auto" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false"
                            class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-brand-100 text-brand-700 text-sm font-semibold uppercase">
                            {{ Str::substr($user->name, 0, 1) }}
                        </span>
                        <span class="hidden sm:block text-sm font-medium text-slate-700">{{ $user->name }}</span>
                        <x-icon name="chevron-down" class="w-4 h-4 text-slate-400" />
                    </button>
                    <div x-show="open" x-cloak x-transition
                         class="absolute right-4 mt-2 w-48 rounded-lg bg-white border border-slate-200 shadow-lg py-1">
                        <div class="px-4 py-2 border-b border-slate-100">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ $user->name }}</p>
                            <p class="text-xs text-slate-500">{{ '@'.$user->username }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <x-icon name="logout" class="w-4 h-4" /> Keluar
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 max-w-7xl mx-auto">
                <x-flash />
                {{ $slot }}
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
