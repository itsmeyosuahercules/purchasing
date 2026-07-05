<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::rememberForever("setting.{$key}", function () use ($key, $default) {
            return static::query()->where('key', $key)->value('value') ?? $default;
        });
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.{$key}");
    }

    public static function defaults(): array
    {
        return [
            'company_name' => 'Nama Perusahaan',
            'company_email' => 'Office@globsrc.com',
            'wechat_contact' => '',
            'whatsapp_contact' => '089601811756',
            'admin_email' => '',
            'ship_to' => 'Will be notified before delivery',
            'payment_terms' => 'As Usual',
            'shipping_method' => 'As Usual',
            'incoterms' => 'Exworks',
            'currency' => 'IDR',
            'po_validity_days' => '30',
            'default_delivery_days' => '14',
            'terms_conditions' => "1. This Purchase Order (PO) number must appear on all invoices, packing slips, cartons, and correspondence related to this order.\n2. Acceptance of this PO constitutes agreement to all terms herein. Any changes must be agreed in writing by the Buyer.\n3. Prices are fixed and inclusive of the quantities, units, and specifications stated. No additional charges are payable unless authorized in writing.\n4. Goods must be delivered to the Ship-To address by the stated delivery date. The Buyer reserves the right to reject late, damaged, or non-conforming goods.\n5. Title and risk of loss pass to the Buyer upon acceptance of conforming goods at the delivery location, per the stated Incoterms.\n6. Invoices are payable according to the stated Payment Terms from the date of a correct invoice and acceptance of goods.\n7. The Vendor warrants that all goods are new, free from defects, and comply with applicable laws, specifications, and agreed quality standards.\n8. The Buyer may cancel all or part of this PO if the Vendor fails to meet delivery, quality, or other material obligations.",
            'default_email_template' => "Yth. {supplier_name},\n\nBerikut pesanan dari {company_name} (No. {order_number}):\n\n{items_list}\n\nMohon konfirmasi ketersediaan. Terima kasih.",
            'default_whatsapp_template' => "Halo {supplier_name},\n\nPesanan dari {company_name} (No. {order_number}):\n\n{items_list_no_price}\n\nMohon konfirmasi ketersediaan. Terima kasih.\n\n{pdf_download_link}",
            'owner_whatsapp_template' => "[Salinan Owner] PO {order_number} disetujui ke {supplier_name}:\n\n{items_list}\n\n{pdf_download_link}",
            'owner_email_template' => "[Salinan Owner] PO {order_number} telah disetujui dan dikirim ke {supplier_name}.\n\nDetail pesanan:\n{items_list}\n\nLampiran: PDF Purchase Order.",
        ];
    }
}
