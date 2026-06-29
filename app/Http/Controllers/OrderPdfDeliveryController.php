<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderPdfService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class OrderPdfDeliveryController extends Controller
{
    public function __construct(private OrderPdfService $pdfService) {}

    /**
     * Endpoint publik ber-signature untuk server WatZap mengunduh PDF PO.
     */
    public function show(Order $order, string $filename): Response
    {
        if ($order->status !== OrderStatus::Approved) {
            abort(404);
        }

        $expected = $this->pdfService->filename($order);

        if ($filename !== $expected) {
            abort(404);
        }

        try {
            $cached = $this->pdfService->downloadFromDeliveryCache($order);

            if ($cached !== null) {
                return $cached;
            }

            $this->pdfService->ensureDeliveryCache($order);

            return $this->pdfService->downloadFromDeliveryCache($order)
                ?? $this->pdfService->make($order)->download($expected);
        } catch (\Throwable $e) {
            Log::error('Gagal menyajikan PDF untuk WatZap', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            abort(500, 'PDF tidak dapat dibuat.');
        }
    }
}
