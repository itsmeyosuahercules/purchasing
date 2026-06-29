<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'pendingCount' => Order::query()->where('status', OrderStatus::Pending)->count(),
            'approvedCount' => Order::query()->where('status', OrderStatus::Approved)->count(),
            'supplierCount' => Supplier::query()->count(),
            'productCount' => Product::query()->count(),
            'employeeCount' => User::query()->where('role', 'employee')->count(),
            'pendingValue' => $this->valueForStatus(OrderStatus::Pending),
            'approvedValueThisMonth' => $this->approvedValueThisMonth(),
            'recentOrders' => Order::query()
                ->with(['user:id,name', 'supplier:id,real_name,alias_name'])
                ->latest()
                ->limit(6)
                ->get(),
        ]);
    }

    private function valueForStatus(OrderStatus $status): float
    {
        return (float) OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('status', $status))
            ->sum(DB::raw('quantity * price'));
    }

    private function approvedValueThisMonth(): float
    {
        return (float) OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('status', OrderStatus::Approved)
                ->whereBetween('approved_at', [now()->startOfMonth(), now()->endOfMonth()]))
            ->sum(DB::raw('quantity * price'));
    }
}
