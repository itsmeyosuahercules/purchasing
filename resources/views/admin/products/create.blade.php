<x-app-layout title="Tambah Produk">
    <div class="mb-5">
        <a href="{{ route('admin.products.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900">
            <x-icon name="arrow-left" class="w-4 h-4" /> Kembali ke daftar
        </a>
    </div>
    @include('admin.products._form', ['product' => null, 'suppliers' => $suppliers])
</x-app-layout>
