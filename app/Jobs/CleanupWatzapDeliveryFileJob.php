<?php

namespace App\Jobs;

use App\Services\OrderPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanupWatzapDeliveryFileJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $relativePath) {}

    public function handle(OrderPdfService $pdfService): void
    {
        $pdfService->cleanupWatzapPublication($this->relativePath);
    }
}
