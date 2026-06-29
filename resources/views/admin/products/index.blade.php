<x-app-layout title="Produk">
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-slate-500">Harga hanya terlihat oleh admin.</p>
        <x-button href="{{ route('admin.products.create') }}">
            <x-icon name="plus" class="w-4 h-4" /> Tambah Produk
        </x-button>
    </div>

    <x-table.toolbar placeholder="Cari nama produk / supplier...">
        <x-slot:filters>
            <select name="supplier_id" x-data x-on:change="$el.form.requestSubmit()" class="field-input w-auto">
                <option value="">Semua Supplier</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->alias_name }}</option>
                @endforeach
            </select>
        </x-slot:filters>
    </x-table.toolbar>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <x-table.heading column="name">Nama</x-table.heading>
                        <x-table.heading :sortable="false">Supplier</x-table.heading>
                        <x-table.heading column="price" align="right">Harga</x-table.heading>
                        <x-table.heading column="unit">Satuan</x-table.heading>
                        <x-table.heading column="is_active">Status</x-table.heading>
                        <x-table.heading :sortable="false" align="right">Aksi</x-table.heading>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-medium text-slate-900">{{ $product->name }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $product->supplier->alias_name }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums">Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                            <td class="px-5 py-3.5">{{ $product->unit }}</td>
                            <td class="px-5 py-3.5">
                                @if($product->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Aktif</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-500/20">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.products.edit', $product) }}" class="p-2 text-slate-400 hover:text-brand-600" title="Edit">
                                        <x-icon name="pencil" class="w-4 h-4" />
                                    </a>
                                    <form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Hapus produk ini?')">
                                        @csrf @method('DELETE')
                                        <button class="p-2 text-slate-400 hover:text-red-600" title="Hapus">
                                            <x-icon name="trash" class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">
                            {{ request()->hasAny(['search', 'supplier_id']) ? 'Tidak ada produk yang cocok.' : 'Belum ada produk.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $products->links() }}</div>
</x-app-layout>
