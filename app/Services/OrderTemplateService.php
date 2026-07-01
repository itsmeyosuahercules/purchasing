<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;

class OrderTemplateService
{
    public function render(Order $order, string $template, ?string $pdfDownloadLink = null): string
    {
        $order->loadMissing(['supplier', 'items']);

        $pdfLine = $pdfDownloadLink
            ? "Unduh Purchase Order (PDF):\n{$pdfDownloadLink}"
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

    private function formatItemsList(Order $order, bool $withPrice): string
    {
        return $order->items
            ->map(function ($item) use ($withPrice) {
                $line = "- {$item->product_name}: {$item->quantity} {$item->unit}";

                if ($withPrice) {
                    $price = number_format((float) $item->price, 0, ',', '.');
                    $line .= " (@ Rp {$price})";
                }

                return $line;
            })
            ->implode("\n");
    }
}
