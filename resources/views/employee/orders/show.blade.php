<x-app-layout :title="$order->order_number">
    <div class="mb-5">
        <a href="{{ route('employee.orders.history') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900">
            <x-icon name="arrow-left" class="w-4 h-4" /> Kembali ke riwayat
        </a>
    </div>

    <div class="max-w-3xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-slate-900">{{ $order->order_number }}</h2>
                <x-badge :status="$order->status" />
            </div>
            <div class="flex items-center gap-2">
                <x-button href="{{ route('employee.orders.pdf.preview', $order) }}" variant="secondary" class="py-2! px-3! text-xs">
                    <x-icon name="document" class="w-4 h-4" /> Preview PDF
                </x-button>
                <p class="text-sm text-slate-500">{{ $order->created_at->format('d/m/Y H:i') }}</p>
            </div>
        </div>

        <x-card>
            <div class="flex items-center gap-2 text-sm">
                <span class="text-slate-500">Supplier:</span>
                <span class="font-medium text-slate-900">{{ $order->supplier->alias_name }}</span>
            </div>
        </x-card>

        <x-card title="Barang yang Dipesan" :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">Produk</th>
                            <th class="px-5 py-3 text-right font-medium">Jumlah</th>
                            <th class="px-5 py-3 text-left font-medium">Satuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 py-3.5 font-medium text-slate-900">{{ $item->product_name }}</td>
                                <td class="px-5 py-3.5 text-right tabular-nums">{{ rtrim(rtrim(number_format($item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="px-5 py-3.5">{{ $item->unit }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        @if($order->status->value === 'rejected')
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                Pesanan ini ditolak admin. Silakan buat pesanan baru bila perlu.
            </div>
        @endif

        <p class="text-xs text-slate-400">Riwayat ini untuk verifikasi dan pertanggungjawaban.</p>
    </div>
</x-app-layout>
