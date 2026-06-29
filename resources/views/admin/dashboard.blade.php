<x-app-layout title="Dashboard">
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-4">
        <x-stat-card label="Menunggu Persetujuan" :value="$pendingCount" icon="clock" tone="amber" :href="route('admin.orders.index', ['status' => 'pending'])" />
        <x-stat-card label="Disetujui" :value="$approvedCount" icon="check" tone="green" :href="route('admin.orders.index', ['status' => 'approved'])" />
        <x-stat-card label="Supplier" :value="$supplierCount" icon="suppliers" tone="brand" :href="route('admin.suppliers.index')" />
        <x-stat-card label="Produk" :value="$productCount" icon="products" tone="slate" :href="route('admin.products.index')" />
        <x-stat-card label="Karyawan" :value="$employeeCount" icon="users" tone="brand" :href="route('admin.users.index')" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-sm text-slate-500">Nilai Pesanan Menunggu</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">Rp {{ number_format($pendingValue, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <p class="text-sm text-slate-500">Nilai Disetujui Bulan Ini</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">Rp {{ number_format($approvedValueThisMonth, 0, ',', '.') }}</p>
        </div>
    </div>

    <x-card title="Pesanan Terbaru" :padding="false">
        <x-slot:actions>
            <x-button href="{{ route('admin.orders.index') }}" variant="secondary" class="py-1.5! px-3! text-xs">Lihat semua</x-button>
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">No. Order</th>
                        <th class="px-5 py-3 text-left font-medium">Karyawan</th>
                        <th class="px-5 py-3 text-left font-medium">Supplier</th>
                        <th class="px-5 py-3 text-left font-medium">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr class="border-t border-slate-100 hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-medium text-slate-900">{{ $order->order_number }}</td>
                            <td class="px-5 py-3.5">{{ $order->user->name }}</td>
                            <td class="px-5 py-3.5">{{ $order->supplier->real_name }} <span class="text-slate-400">({{ $order->supplier->alias_name }})</span></td>
                            <td class="px-5 py-3.5"><x-badge :status="$order->status" /></td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-brand-600 font-medium hover:underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-400">Belum ada pesanan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
