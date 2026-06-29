<form method="POST"
      action="{{ $product ? route('admin.products.update', $product) : route('admin.products.store') }}"
      class="max-w-xl space-y-5">
    @csrf
    @if($product) @method('PUT') @endif

    <x-card title="Detail Produk">
        <div class="space-y-4">
            @if($suppliers->isEmpty())
                <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                    Belum ada supplier aktif. Tambahkan supplier terlebih dahulu.
                </p>
            @endif

            <x-select name="supplier_id" label="Supplier">
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" @selected(old('supplier_id', $product?->supplier_id) == $s->id)>{{ $s->alias_name }} ({{ $s->real_name }})</option>
                @endforeach
            </x-select>

            <x-input name="name" label="Item Name" :value="$product?->name" required />

            <x-textarea name="item_content" label="Item Content" rows="2" :value="$product?->item_content"
                        hint="What's in the box — contoh: botol + tutup + sedotan + buku petunjuk" />

            <div class="grid md:grid-cols-2 gap-4">
                <x-input name="native_supplier_pn" label="Native Supplier P/N" :value="$product?->native_supplier_pn" />
                <x-input name="brand" label="Brand" :value="$product?->brand" />
            </div>

            <x-input name="unit" label="Unit of Measure" :value="$product?->unit" placeholder="pcs, kg, liter" required />

            <x-textarea name="description" label="Description" rows="3" :value="$product?->description" />

            <x-rupiah-input name="price" label="Unit Price" :value="$product?->price" hint="Otomatis terformat, cukup ketik angka." required />

            <label class="flex items-center gap-3 text-sm text-slate-700 select-none">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product?->is_active ?? true))
                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Produk aktif
            </label>
        </div>
    </x-card>

    <div class="flex gap-3">
        <x-button type="submit">Simpan</x-button>
        <x-button href="{{ route('admin.products.index') }}" variant="secondary">Batal</x-button>
    </div>
</form>
