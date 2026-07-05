<x-app-layout title="Pengaturan">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-3xl space-y-5">
        @csrf @method('PUT')

        <x-card title="Identitas Perusahaan">
            <div class="space-y-4">
                <x-input name="company_name" label="Nama Perusahaan" :value="$settings['company_name']"
                         hint="Dipakai pada header Purchase Order." required />
                <div class="grid md:grid-cols-2 gap-4">
                    <x-input name="company_email" type="email" label="Email Perusahaan" :value="$settings['company_email']"
                             hint="Contoh: Office@globsrc.com" />
                    <x-input name="wechat_contact" label="WeChat" :value="$settings['wechat_contact']"
                             placeholder="ID WeChat perusahaan" />
                    <x-input name="whatsapp_contact" label="WhatsApp Owner" :value="$settings['whatsapp_contact']"
                             placeholder="089601811756"
                             hint="Nomor owner: tampil di PDF + salinan WhatsApp setiap PO disetujui." />
                </div>
                <x-input name="admin_email" type="email" label="Email Owner" :value="$settings['admin_email']"
                         hint="Salinan email PO (dengan PDF) dikirim ke sini setiap pesanan disetujui." />
            </div>
        </x-card>

        <x-card title="Default Purchase Order" subtitle="Nilai baku yang muncul di PDF PO.">
            <div class="space-y-4">
                <x-textarea name="ship_to" label="Ship To" rows="2" :value="$settings['ship_to']" />
                <div class="grid md:grid-cols-2 gap-4">
                    <x-input name="payment_terms" label="Payment Terms" :value="$settings['payment_terms']" />
                    <x-input name="shipping_method" label="Shipping Method" :value="$settings['shipping_method']" />
                    <x-input name="incoterms" label="Incoterms / FOB" :value="$settings['incoterms']" />
                    <x-input name="currency" label="Currency" :value="$settings['currency']" />
                    <x-input name="po_validity_days" type="number" label="Masa Berlaku PO (hari)" :value="$settings['po_validity_days']"
                             hint="Dihitung dari tanggal approve jika belum diisi manual." />
                    <x-input name="default_delivery_days" type="number" label="Default Delivery Date (hari)" :value="$settings['default_delivery_days']"
                             hint="Dihitung dari tanggal approve jika belum diisi manual." />
                </div>
                <x-textarea name="terms_conditions" label="Terms & Conditions" rows="10" :value="$settings['terms_conditions']"
                            hint="Satu ketentuan per baris, boleh diawali angka." />
            </div>
        </x-card>

        <x-card title="Template Pesan ke Supplier" subtitle="Dipakai jika supplier tidak punya template khusus.">
            <div class="space-y-4">
                <x-textarea name="default_email_template" label="Template Email" rows="6" :value="$settings['default_email_template']"
                            hint="Placeholder: {company_name}, {supplier_name}, {order_number}, {date}, {items_list}" />
                <x-textarea name="default_whatsapp_template" label="Template WhatsApp" rows="6" :value="$settings['default_whatsapp_template']"
                            hint="Placeholder: {company_name}, {supplier_name}, {order_number}, {date}, {items_list_no_price}, {pdf_download_link}" />
            </div>
        </x-card>

        <x-card title="Template Salinan Owner" subtitle="Dikirim ke Email Owner & WhatsApp Owner di atas setiap PO disetujui.">
            <div class="space-y-4">
                <x-textarea name="owner_email_template" label="Template Email (Salinan Owner)" rows="5" :value="$settings['owner_email_template']"
                            hint="Placeholder: {company_name}, {supplier_name}, {order_number}, {date}, {items_list}" />
                <x-textarea name="owner_whatsapp_template" label="Template WhatsApp (Salinan Owner)" rows="5" :value="$settings['owner_whatsapp_template']"
                            hint="Placeholder: {company_name}, {supplier_name}, {order_number}, {date}, {items_list}, {pdf_download_link}" />
            </div>
        </x-card>

        <x-button type="submit">Simpan Pengaturan</x-button>
    </form>
</x-app-layout>
