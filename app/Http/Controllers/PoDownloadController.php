<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Services\OrderPdfService;
use Symfony\Component\HttpFoundation\Response;

class PoDownloadController extends Controller
{
    public function __construct(private OrderPdfService $pdfService) {}

    /**
     * Link pendek /po/{token} — unduh PDF PO (publik, token acak).
     */
    public function show(string $token): Response
    {
        $order = $this->pdfService->findOrderByDownloadToken($token);

        if (! $order || $order->status !== OrderStatus::Approved) {
            abort(404);
        }

        $cached = $this->pdfService->downloadFromDeliveryCache($order);

        if ($cached !== null) {
            return $cached;
        }

        $this->pdfService->ensureDeliveryCache($order);

        return $this->pdfService->downloadFromDeliveryCache($order)
            ?? $this->pdfService->make($order)->download($this->pdfService->filename($order));
    }
}
