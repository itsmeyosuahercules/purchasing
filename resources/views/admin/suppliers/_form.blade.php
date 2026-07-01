<form method="POST"
      action="{{ $supplier ? route('admin.suppliers.update', $supplier) : route('admin.suppliers.store') }}"
      class="max-w-3xl space-y-5">
    @csrf
    @if($supplier) @method('PUT') @endif

    <x-card title="Informasi Supplier" subtitle="Nama asli & kontak tidak akan pernah terlihat oleh karyawan.">
        <div class="space-y-4">
            <div class="grid md:grid-cols-2 gap-4">
                <x-input name="real_name" label="Nama Asli" :value="$supplier?->real_name" required />
                <x-input name="alias_name" label="Nama Samaran (untuk karyawan)" :value="$supplier?->alias_name" required />
            </div>
            <x-input name="contact_person" label="Contact Person (Attention)" :value="$supplier?->contact_person"
                     hint="Muncul di PDF PO sebagai Attention." />
            <div class="grid md:grid-cols-2 gap-4">
                <x-input name="email" type="email" label="Email Supplier" :value="$supplier?->email" required />
                <x-input name="whatsapp" label="Nomor WhatsApp" :value="$supplier?->whatsapp" placeholder="62812xxxxxxx" hint="Format internasional, mis. 62812..." required />
            </div>
        </div>
    </x-card>

    <x-card title="Template Pesan (opsional)" subtitle="Kosongkan untuk memakai template default dari Pengaturan.">
        <div class="space-y-4">
            <x-textarea name="email_template" label="Template Email" :value="$supplier?->email_template"
                        hint="Placeholder: {company_name}, {supplier_name}, {order_number}, {date}, {items_list}" />
            <x-textarea name="whatsapp_template" label="Template WhatsApp" :value="$supplier?->whatsapp_template"
                        hint="Kosongkan = pakai default Pengaturan. Placeholder: {company_name}, {supplier_name}, {order_number}, {date}, {items_list_no_price}, {pdf_download_link} (link unduh PDF — mode link)" />
        </div>
    </x-card>

    <x-card>
        <label class="flex items-center gap-3 text-sm text-slate-700 select-none">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier?->is_active ?? true))
                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            Supplier aktif (bisa dipilih saat membuat pesanan)
        </label>
    </x-card>

    <div class="flex gap-3">
        <x-button type="submit">Simpan</x-button>
        <x-button href="{{ route('admin.suppliers.index') }}" variant="secondary">Batal</x-button>
    </div>
</form>
