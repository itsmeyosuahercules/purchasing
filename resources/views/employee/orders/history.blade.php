<x-app-layout title="Riwayat Pesanan">
    <p class="text-sm text-slate-500 mb-5">Riwayat pesanan yang Anda buat. Harga dan kontak supplier tidak ditampilkan.</p>

    <x-table.toolbar placeholder="Cari no. order / supplier..." />

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <x-table.heading column="order_number">No. Order</x-table.heading>
                        <x-table.heading column="created_at">Tanggal</x-table.heading>
                        <x-table.heading :sortable="false">Supplier</x-table.heading>
                        <x-table.heading :sortable="false">Item</x-table.heading>
                        <x-table.heading column="status">Status</x-table.heading>
                        <x-table.heading :sortable="false" align="right">Aksi</x-table.heading>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-medium text-slate-900">{{ $order->order_number }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-3.5">{{ $order->supplier->alias_name }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $order->items_count }} item</td>
                            <td class="px-5 py-3.5"><x-badge :status="$order->status" /></td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('employee.orders.show', $order) }}" class="text-brand-600 font-medium hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">
                            {{ request('search') ? 'Tidak ada pesanan yang cocok.' : 'Belum ada pesanan.' }}
                            @unless(request('search')) <a href="{{ route('employee.orders.create') }}" class="text-brand-600 hover:underline">Buat sekarang</a>. @endunless
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $orders->links() }}</div>
</x-app-layout>
