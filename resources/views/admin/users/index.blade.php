<x-app-layout title="Pengguna">
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-slate-500">Kelola akun admin & karyawan.</p>
        <x-button href="{{ route('admin.users.create') }}">
            <x-icon name="plus" class="w-4 h-4" /> Tambah Pengguna
        </x-button>
    </div>

    <x-table.toolbar placeholder="Cari nama / username / email..." />

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <x-table.heading column="name">Nama</x-table.heading>
                        <x-table.heading column="username">Username</x-table.heading>
                        <x-table.heading column="role">Role</x-table.heading>
                        <x-table.heading column="is_active">Status</x-table.heading>
                        <x-table.heading :sortable="false" align="right">Aksi</x-table.heading>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-medium text-slate-900">{{ $user->name }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ '@'.$user->username }}</td>
                            <td class="px-5 py-3.5">
                                @if($user->isAdmin())
                                    <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-600/20">Admin</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20">Karyawan</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                @if($user->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Aktif</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-500/20">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="p-2 text-slate-400 hover:text-brand-600" title="Edit">
                                        <x-icon name="pencil" class="w-4 h-4" />
                                    </a>
                                    @if($user->id !== auth()->id())
                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Hapus pengguna ini?')">
                                            @csrf @method('DELETE')
                                            <button class="p-2 text-slate-400 hover:text-red-600" title="Hapus">
                                                <x-icon name="trash" class="w-4 h-4" />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-400">
                            {{ request('search') ? 'Tidak ada pengguna yang cocok.' : 'Belum ada pengguna.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>
