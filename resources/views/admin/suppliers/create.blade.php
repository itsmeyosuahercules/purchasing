<x-app-layout title="Tambah Supplier">
    <div class="mb-5">
        <a href="{{ route('admin.suppliers.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900">
            <x-icon name="arrow-left" class="w-4 h-4" /> Kembali ke daftar
        </a>
    </div>
    @include('admin.suppliers._form', ['supplier' => null])
</x-app-layout>
