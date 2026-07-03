<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\OrderPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCopyToAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Konfirmasi] Pesanan {$this->order->order_number} telah disetujui",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.order-plain',
            with: ['body' => $this->body],
        );
    }

    public function attachments(): array
    {
        return [$this->pdfAttachment()];
    }

    private function pdfAttachment(): Attachment
    {
        $service = app(OrderPdfService::class);
        $service->ensureDeliveryCache($this->order);

        return Attachment::fromStorageDisk(
            'local',
            $service->deliveryCachePath($this->order),
            $service->filename($this->order),
        )->withMime('application/pdf');
    }
}
