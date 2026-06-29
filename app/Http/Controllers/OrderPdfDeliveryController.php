<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderPdfService;
use Symfony\Component\HttpFoundation\Response;

class OrderPdfDeliveryController extends Controller
{
    public function __construct(private OrderPdfService $pdfService) {}

    /**
     * Endpoint publik ber-signature untuk server WatZap mengunduh PDF PO.
     * Nama file ada di URL agar WatZap tidak menampilkan "pdf" saja.
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

        $pdf = $this->pdfService->make($order);

        return $pdf->download($expected);
    }
}
