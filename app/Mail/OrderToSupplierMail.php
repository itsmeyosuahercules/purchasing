<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use App\Services\OrderPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderToSupplierMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        $company = Setting::get('company_name', config('app.name'));

        return new Envelope(
            subject: "Pesanan dari {$company} - {$this->order->order_number}",
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
