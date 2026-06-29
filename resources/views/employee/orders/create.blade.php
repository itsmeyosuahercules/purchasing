<x-app-layout title="Buat Pesanan">
    <div class="max-w-3xl">
        <p class="text-sm text-slate-500 mb-5">
            Pesanan dibuat atas nama perusahaan dan akan dikirim ke supplier setelah disetujui admin.
        </p>

        @error('items')
            <div class="mb-5 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('employee.orders.store') }}" x-data="orderForm()" x-init="init()" class="space-y-5">
            @csrf

            <x-card title="Pilih Supplier">
                <select name="supplier_id" x-model="supplierId" @change="loadProducts()" required class="field-input">
                    <option value="">Pilih supplier...</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->alias_name }}</option>
                    @endforeach
                </select>
            </x-card>

            <x-card title="Daftar Barang">
                <x-slot:actions>
                    <x-button type="button" variant="secondary" class="py-1.5! px-3! text-xs"
                              x-bind:disabled="!products.length" @click="addItem()">
                        <x-icon name="plus" class="w-4 h-4" /> Tambah Barang
                    </x-button>
                </x-slot:actions>

                <div x-show="!supplierId" class="text-sm text-slate-400 py-6 text-center">
                    Pilih supplier terlebih dahulu.
                </div>

                <div x-show="loading" x-cloak class="text-sm text-slate-400 py-6 text-center">Memuat produk...</div>

                <div x-show="supplierId && !loading && !products.length" x-cloak class="text-sm text-amber-600 py-6 text-center">
                    Supplier ini belum punya produk.
                </div>

                <div x-show="supplierId && !loading && products.length" x-cloak class="space-y-3">
                    <template x-for="(item, index) in items" :key="item.key">
                        <div class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 p-3 bg-slate-50/50">
                            <div class="flex-1 min-w-[200px]">
                                <label class="text-xs text-slate-500 mb-1 block">Barang</label>
                                <select :name="`items[${index}][product_id]`" x-model="item.product_id" required class="field-input">
                                    <option value="">Pilih barang...</option>
                                    <template x-for="p in products" :key="p.id">
                                        <option :value="p.id" x-text="`${p.name} (${p.unit})`"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="w-32">
                                <label class="text-xs text-slate-500 mb-1 block">Jumlah</label>
                                <input type="number" step="0.01" min="0.01" :name="`items[${index}][quantity]`"
                                       x-model="item.quantity" required class="field-input">
                            </div>
                            <button type="button" @click="removeItem(index)" class="p-2.5 text-slate-400 hover:text-red-600" title="Hapus">
                                <x-icon name="trash" class="w-4 h-4" />
                            </button>
                        </div>
                    </template>
                </div>
            </x-card>

            <x-button type="submit" x-bind:disabled="!canSubmit">Kirim Permintaan</x-button>
        </form>
    </div>

    @push('scripts')
    <script>
        function orderForm() {
            return {
                supplierId: @json(old('supplier_id', '')),
                products: [],
                items: [],
                loading: false,
                get canSubmit() {
                    return this.supplierId && this.items.length > 0 &&
                        this.items.every(i => i.product_id && i.quantity > 0);
                },
                async init() {
                    if (this.supplierId) {
                        await this.loadProducts(true);
                    }
                },
                async loadProducts(keepOld = false) {
                    this.items = [];
                    this.products = [];
                    if (!this.supplierId) return;
                    this.loading = true;
                    try {
                        const res = await fetch(`/employee/suppliers/${this.supplierId}/products`, {
                            headers: { 'Accept': 'application/json' },
                        });
                        this.products = res.ok ? await res.json() : [];
                    } catch (e) {
                        this.products = [];
                    }
                    this.loading = false;

                    const oldItems = keepOld ? @json(old('items', [])) : [];
                    if (oldItems.length) {
                        oldItems.forEach(i => this.items.push({
                            key: Math.random(),
                            product_id: i.product_id ?? '',
                            quantity: i.quantity ?? '',
                        }));
                    } else if (this.products.length) {
                        this.addItem();
                    }
                },
                addItem() {
                    this.items.push({ key: Math.random(), product_id: '', quantity: '' });
                },
                removeItem(index) {
                    this.items.splice(index, 1);
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
