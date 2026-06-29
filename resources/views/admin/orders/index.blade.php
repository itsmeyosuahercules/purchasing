<x-app-layout title="Pesanan">
    <x-table.toolbar placeholder="Cari no. order / karyawan / supplier...">
        <x-slot:filters>
            <select name="status" x-data x-on:change="$el.form.requestSubmit()" class="field-input w-auto">
                <option value="">Semua Status</option>
                @foreach($statuses as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </x-slot:filters>
        <x-slot:trailing>
            <x-button :href="route('admin.orders.export', request()->query())" variant="secondary" class="py-2! px-3! text-xs whitespace-nowrap">
                <x-icon name="document" class="w-4 h-4" /> Export Excel
            </x-button>
        </x-slot:trailing>
    </x-table.toolbar>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <x-table.heading column="order_number">No. Order</x-table.heading>
                        <x-table.heading column="created_at">Tanggal</x-table.heading>
                        <x-table.heading :sortable="false">Karyawan</x-table.heading>
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
                            <td class="px-5 py-3.5">{{ $order->user->name }}</td>
                            <td class="px-5 py-3.5">{{ $order->supplier->real_name }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $order->items_count }} item</td>
                            <td class="px-5 py-3.5"><x-badge :status="$order->status" /></td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-brand-600 font-medium hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">
                            {{ request()->hasAny(['search', 'status']) ? 'Tidak ada pesanan yang cocok.' : 'Tidak ada pesanan.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $orders->links() }}</div>
</x-app-layout>
