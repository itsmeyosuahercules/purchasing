<?php

namespace App\Http\Controllers\Employee;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\OrderNumberService;
use App\Services\OrderPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private OrderNumberService $orderNumberService,
        private OrderPdfService $pdfService,
    ) {}

    public function create()
    {
        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->orderBy('alias_name')
            ->get(['id', 'alias_name']);

        return view('employee.orders.create', compact('suppliers'));
    }

    public function products(Supplier $supplier)
    {
        abort_unless($supplier->is_active, 404);

        return response()->json(
            $supplier->activeProducts()
                ->orderBy('name')
                ->get(['id', 'name', 'unit'])
        );
    }

    public function store(StoreOrderRequest $request)
    {
        $data = $request->validated();

        $supplier = Supplier::query()->where('is_active', true)->findOrFail($data['supplier_id']);

        $productIds = collect($data['items'])->pluck('product_id')->unique();
        $products = Product::query()
            ->where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            return back()->withInput()->with('error', 'Ada barang yang tidak valid untuk supplier ini.');
        }

        $order = DB::transaction(function () use ($data, $supplier, $products) {
            $order = Order::query()->create([
                'order_number' => $this->orderNumberService->generate(),
                'user_id' => auth()->id(),
                'supplier_id' => $supplier->id,
                'status' => OrderStatus::Pending,
            ]);

            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']];
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'item_content' => $product->item_content,
                    'native_supplier_pn' => $product->native_supplier_pn,
                    'brand' => $product->brand,
                    'description' => $product->description,
                    'quantity' => $item['quantity'],
                    'unit' => $product->unit,
                    'price' => $product->price,
                ]);
            }

            return $order;
        });

        return redirect()->route('employee.orders.show', $order)
            ->with('success', 'Permintaan pesanan berhasil dikirim. Menunggu persetujuan admin.');
    }

    public function history()
    {
        $query = Order::query()
            ->where('user_id', auth()->id())
            ->with('supplier:id,alias_name')
            ->withCount('items');

        $orders = \App\Support\TableQuery::paginate($query, [
            'searchable' => ['order_number', 'supplier.alias_name'],
            'sortable' => ['order_number', 'status', 'created_at'],
            'default' => ['created_at', 'desc'],
        ]);

        return view('employee.orders.history', compact('orders'));
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['supplier:id,alias_name', 'items']);

        return view('employee.orders.show', compact('order'));
    }

    public function pdfPreview(Order $order)
    {
        $this->authorize('view', $order);

        return view('orders.pdf-preview', [
            'order' => $order,
            'backUrl' => route('employee.orders.show', $order),
            'previewUrl' => route('employee.orders.pdf.inline', $order),
            'downloadUrl' => route('employee.orders.pdf.download', $order),
            'subtitle' => 'Versi tanpa harga dan kontak supplier.',
        ]);
    }

    public function pdfInline(Order $order)
    {
        $this->authorize('view', $order);

        $pdf = $this->pdfService->make($order, forEmployee: true);

        return $pdf->stream($this->pdfService->filename($order));
    }

    public function pdfDownload(Order $order)
    {
        $this->authorize('view', $order);

        $pdf = $this->pdfService->make($order, forEmployee: true);

        return $pdf->download($this->pdfService->filename($order));
    }
}
