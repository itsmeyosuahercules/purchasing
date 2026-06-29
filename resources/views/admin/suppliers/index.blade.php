<x-app-layout title="Supplier">
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-slate-500">Data asli supplier hanya terlihat oleh admin.</p>
        <x-button href="{{ route('admin.suppliers.create') }}">
            <x-icon name="plus" class="w-4 h-4" /> Tambah Supplier
        </x-button>
    </div>

    <x-table.toolbar placeholder="Cari nama, email, WhatsApp..." />

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <x-table.heading column="real_name">Nama Asli</x-table.heading>
                        <x-table.heading column="alias_name">Samaran</x-table.heading>
                        <x-table.heading column="email">Email</x-table.heading>
                        <x-table.heading :sortable="false">WhatsApp</x-table.heading>
                        <x-table.heading :sortable="false">Produk</x-table.heading>
                        <x-table.heading column="is_active">Status</x-table.heading>
                        <x-table.heading :sortable="false" align="right">Aksi</x-table.heading>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-medium text-slate-900">{{ $supplier->real_name }}</td>
                            <td class="px-5 py-3.5">{{ $supplier->alias_name }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $supplier->email }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $supplier->whatsapp }}</td>
                            <td class="px-5 py-3.5">{{ $supplier->products_count }}</td>
                            <td class="px-5 py-3.5">
                                @if($supplier->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Aktif</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-500/20">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="p-2 text-slate-400 hover:text-brand-600" title="Edit">
                                        <x-icon name="pencil" class="w-4 h-4" />
                                    </a>
                                    <form action="{{ route('admin.suppliers.destroy', $supplier) }}" method="POST"
                                          onsubmit="return confirm('Hapus supplier ini? Riwayat pesanan tetap tersimpan.')">
                                        @csrf @method('DELETE')
                                        <button class="p-2 text-slate-400 hover:text-red-600" title="Hapus">
                                            <x-icon name="trash" class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">
                            {{ request('search') ? 'Tidak ada supplier yang cocok dengan pencarian.' : 'Belum ada supplier. Tambahkan yang pertama.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $suppliers->links() }}</div>
</x-app-layout>
