<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Requests\UpdateOrderPoRequest;
use App\Exports\OrdersExport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderApprovalService;
use App\Services\OrderPdfService;
use App\Support\TableQuery;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderController extends Controller
{
    public function __construct(
        private OrderApprovalService $approvalService,
        private OrderPdfService $pdfService,
    ) {}

    public function index(Request $request)
    {
        $orders = TableQuery::paginate($this->filtered($request), [
            'searchable' => ['order_number', 'user.name', 'supplier.real_name', 'supplier.alias_name'],
            'sortable' => ['order_number', 'status', 'created_at'],
            'default' => ['created_at', 'desc'],
        ]);

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = TableQuery::apply($this->filtered($request), [
            'searchable' => ['order_number', 'user.name', 'supplier.real_name', 'supplier.alias_name'],
            'sortable' => ['order_number', 'status', 'created_at'],
            'default' => ['created_at', 'desc'],
        ])->with(['user:id,name', 'supplier:id,real_name,alias_name', 'items']);

        $filename = 'pesanan-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new OrdersExport($query), $filename);
    }

    public function show(Order $order)
    {
        $order->load(['user', 'supplier', 'items', 'approver']);

        return view('admin.orders.show', compact('order'));
    }

    public function approve(Order $order)
    {
        try {
            $this->approvalService->approve($order, auth()->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Pesanan disetujui. Email + WhatsApp (jika aktif) dikirim ke supplier.');
    }

    public function pdfPreview(Order $order)
    {
        return view('orders.pdf-preview', [
            'order' => $order,
            'backUrl' => route('admin.orders.show', $order),
            'previewUrl' => route('admin.orders.pdf.inline', $order),
            'downloadUrl' => route('admin.orders.pdf.download', $order),
            'subtitle' => 'Versi lengkap dengan harga dan kontak supplier.',
        ]);
    }

    public function pdfInline(Order $order)
    {
        $pdf = $this->pdfService->make($order);

        return $pdf->stream($this->pdfService->filename($order));
    }

    public function pdfDownload(Order $order)
    {
        $pdf = $this->pdfService->make($order);

        return $pdf->download($this->pdfService->filename($order));
    }

    public function updatePoDetails(UpdateOrderPoRequest $request, Order $order)
    {
        if ($order->status === OrderStatus::Rejected) {
            return back()->with('error', 'Pesanan yang ditolak tidak dapat diubah.');
        }

        $order->update($request->validated());

        return back()->with('success', 'Detail Purchase Order berhasil disimpan.');
    }

    public function resendEmail(Order $order)
    {
        try {
            $this->approvalService->resendEmail($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Email pesanan berhasil dikirim ulang ke supplier.');
    }

    public function resendWhatsapp(Order $order)
    {
        try {
            $this->approvalService->resendWhatsapp($order);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengirim WhatsApp: '.$e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'WhatsApp sedang dikirim. Tunggu ~10 detik lalu refresh halaman ini.');
    }

    public function reject(Request $request, Order $order)
    {
        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->approvalService->reject($order, auth()->user(), $data['rejection_reason'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Pesanan ditolak.');
    }

    private function filtered(Request $request)
    {
        return Order::query()
            ->with(['user:id,name', 'supplier:id,real_name,alias_name'])
            ->withCount('items')
            ->when($request->enum('status', OrderStatus::class), fn ($q, $status) => $q->where('status', $status));
    }
}
