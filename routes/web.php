<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Employee\OrderController as EmployeeOrderController;
use App\Http\Controllers\OrderPdfDeliveryController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/delivery/orders/{order}/pdf/{filename}', [OrderPdfDeliveryController::class, 'show'])
    ->middleware('signed')
    ->where('filename', '[^/]+')
    ->name('orders.pdf.delivery');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('suppliers', SupplierController::class)->except(['show']);
        Route::resource('products', ProductController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/export', [AdminOrderController::class, 'export'])->name('orders.export');
        Route::get('orders/{order}/pdf/preview', [AdminOrderController::class, 'pdfPreview'])->name('orders.pdf.preview');
        Route::get('orders/{order}/pdf/inline', [AdminOrderController::class, 'pdfInline'])->name('orders.pdf.inline');
        Route::get('orders/{order}/pdf/download', [AdminOrderController::class, 'pdfDownload'])->name('orders.pdf.download');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/approve', [AdminOrderController::class, 'approve'])->name('orders.approve');
        Route::put('orders/{order}/po-details', [AdminOrderController::class, 'updatePoDetails'])->name('orders.po-details');
        Route::post('orders/{order}/resend-email', [AdminOrderController::class, 'resendEmail'])->name('orders.resend-email');
        Route::post('orders/{order}/resend-whatsapp', [AdminOrderController::class, 'resendWhatsapp'])->name('orders.resend-whatsapp');
        Route::post('orders/{order}/reject', [AdminOrderController::class, 'reject'])->name('orders.reject');
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware('role:employee')->prefix('employee')->name('employee.')->group(function () {
        Route::get('orders/create', [EmployeeOrderController::class, 'create'])->name('orders.create');
        Route::get('suppliers/{supplier}/products', [EmployeeOrderController::class, 'products'])->name('suppliers.products');
        Route::post('orders', [EmployeeOrderController::class, 'store'])->name('orders.store');
        Route::get('orders/history', [EmployeeOrderController::class, 'history'])->name('orders.history');
        Route::get('orders/{order}/pdf/preview', [EmployeeOrderController::class, 'pdfPreview'])->name('orders.pdf.preview');
        Route::get('orders/{order}/pdf/inline', [EmployeeOrderController::class, 'pdfInline'])->name('orders.pdf.inline');
        Route::get('orders/{order}/pdf/download', [EmployeeOrderController::class, 'pdfDownload'])->name('orders.pdf.download');
        Route::get('orders/{order}', [EmployeeOrderController::class, 'show'])->name('orders.show');
    });
});
