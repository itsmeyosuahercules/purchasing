<?php

namespace App\Services;

use App\Models\Order;

class OrderNumberService
{
    public function generate(): string
    {
        $latest = Order::query()
            ->where('order_number', 'like', 'PO-%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $sequence = 1;
        if ($latest && preg_match('/PO-(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return 'PO-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
