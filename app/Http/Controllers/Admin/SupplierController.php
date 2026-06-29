<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Support\TableQuery;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = TableQuery::paginate(
            Supplier::query()->withCount('products'),
            [
                'searchable' => ['real_name', 'alias_name', 'email', 'whatsapp'],
                'sortable' => ['real_name', 'alias_name', 'email', 'is_active', 'created_at'],
                'default' => ['created_at', 'desc'],
            ]
        );

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('admin.suppliers.create');
    }

    public function store(SupplierRequest $request)
    {
        Supplier::query()->create($request->validated());

        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier)
    {
        return view('admin.suppliers.edit', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());

        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('admin.suppliers.index')
            ->with('success', 'Supplier berhasil dinonaktifkan dari daftar.');
    }
}
