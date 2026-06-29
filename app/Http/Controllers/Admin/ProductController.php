<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\TableQuery;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with('supplier:id,real_name,alias_name')
            ->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id));

        $products = TableQuery::paginate($query, [
            'searchable' => ['name', 'unit', 'supplier.alias_name', 'supplier.real_name'],
            'sortable' => ['name', 'price', 'unit', 'is_active', 'created_at'],
            'default' => ['created_at', 'desc'],
        ]);

        $suppliers = Supplier::query()->orderBy('alias_name')->get();

        return view('admin.products.index', compact('products', 'suppliers'));
    }

    public function create()
    {
        return view('admin.products.create', [
            'suppliers' => $this->activeSuppliers(),
        ]);
    }

    public function store(ProductRequest $request)
    {
        Product::query()->create($request->validated());

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.edit', [
            'product' => $product,
            'suppliers' => $this->activeSuppliers(),
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    private function activeSuppliers()
    {
        return Supplier::query()->where('is_active', true)->orderBy('alias_name')->get();
    }
}
