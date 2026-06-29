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
     */
    public function show(Order $order): Response
    {
        if ($order->status !== OrderStatus::Approved) {
            abort(404);
        }

        $pdf = $this->pdfService->make($order);

        return $pdf->stream($this->pdfService->filename($order));
    }
}
