<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Support\QuantityFormatter;

class OrderTemplateService
{
    public function render(Order $order, string $template, ?string $pdfDownloadLink = null): string
    {
        $order->loadMissing(['supplier', 'items']);

        $days = (int) config('watzap.pdf_link_ttl_days', 7);

        $pdfLine = $pdfDownloadLink
            ? "Unduh {$order->order_number}:\n{$pdfDownloadLink}\n(Link aktif {$days} hari)"
            : '';

        $replacements = [
            '{company_name}' => Setting::get('company_name', 'Perusahaan'),
            '{supplier_name}' => $order->supplier->real_name,
            '{order_number}' => $order->order_number,
            '{date}' => $order->created_at->format('d/m/Y H:i'),
            '{items_list}' => $this->formatItemsList($order, true),
            '{items_list_no_price}' => $this->formatItemsList($order, false),
            '{pdf_download_link}' => $pdfLine,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public function getEmailTemplate(Order $order): string
    {
        $template = $order->supplier->email_template
            ?: Setting::get('default_email_template', Setting::defaults()['default_email_template']);

        return $this->render($order, $template);
    }

    public function getWhatsappTemplate(Order $order, ?string $pdfDownloadLink = null): string
    {
        $template = $order->supplier->whatsapp_template
            ?: Setting::get('default_whatsapp_template', Setting::defaults()['default_whatsapp_template']);

        return $this->render($order, $template, $pdfDownloadLink);
    }

    public function getOwnerEmailTemplate(Order $order): string
    {
        $template = Setting::get(
            'owner_email_template',
            Setting::defaults()['owner_email_template'],
        );

        return $this->render($order, $template);
    }

    public function getOwnerWhatsappTemplate(Order $order, ?string $pdfDownloadLink = null): string
    {
        $template = Setting::get(
            'owner_whatsapp_template',
            Setting::defaults()['owner_whatsapp_template'],
        );

        return $this->render($order, $template, $pdfDownloadLink);
    }

    private function formatItemsList(Order $order, bool $withPrice): string
    {
        return $order->items
            ->map(function ($item) use ($withPrice) {
                $qty = QuantityFormatter::format($item->quantity);
                $line = "- {$item->product_name}: {$qty} {$item->unit}";

                if ($withPrice) {
                    $price = number_format((float) $item->price, 0, ',', '.');
                    $line .= " (@ Rp {$price})";
                }

                return $line;
            })
            ->implode("\n");
    }
}
