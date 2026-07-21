<?php

use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingCycleMasterPageController;
use App\Http\Controllers\CustomerMasterPageController;
use App\Http\Controllers\FoundationMasterPageController;
use App\Http\Controllers\InventoryPageController;
use App\Http\Controllers\LogisticsPageController;
use App\Http\Controllers\ProductMasterPageController;
use App\Http\Controllers\SalesOrderPageController;
use App\Http\Controllers\TaxPageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/sales-orders');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/sales-orders', [SalesOrderPageController::class, 'index'])
        ->middleware('web.permission:sales_order.view')
        ->name('sales-orders.index');
    Route::get('/shipment-picks', [LogisticsPageController::class, 'picks'])->middleware('web.permission:shipment_pick.view')->name('shipment-picks.index');
    Route::get('/shipments', [LogisticsPageController::class, 'shipments'])->middleware('web.permission:shipment.view')->name('shipments.index');
    Route::get('/shipment-history', [LogisticsPageController::class, 'shipmentHistory'])->middleware('web.permission:shipment.view')->name('shipments.history');
    Route::redirect('/confirmed-shipments', '/shipments')->middleware('web.permission:shipment.view')->name('shipments.confirmed');
    Route::get('/shipments/{shipment}/print', [LogisticsPageController::class, 'printShipment'])->middleware('web.permission:shipment.view')->name('shipments.print');
    Route::get('/shipment-instructions/{shipmentInstruction}/work-slip', [LogisticsPageController::class, 'workSlip'])
        ->middleware('web.permission:shipment_instruction.view')
        ->name('shipment-instructions.work-slip');
    Route::get('/inventory/lot-stock-as-of', [InventoryPageController::class, 'lotStockAsOf'])
        ->middleware('web.permission:inventory.view')
        ->name('inventory.lot-stock-as-of');
    Route::get('/inventory', [InventoryPageController::class, 'index'])
        ->middleware('web.permission:inventory.view')
        ->name('inventory.index');
    Route::get('/inventory/lot-stock-as-of/print', [InventoryPageController::class, 'printLotStockAsOf'])
        ->middleware('web.permission:inventory.view')
        ->name('inventory.lot-stock-as-of.print');
    Route::get('/inventory/movements/print', [InventoryPageController::class, 'printMovements'])
        ->middleware('web.permission:inventory.view')
        ->name('inventory.movements.print');
    Route::get('/billing', [LogisticsPageController::class, 'billing'])->middleware('web.permission:billing.view')->name('billing.index');
    Route::get('/billing/monthly-invoices', [LogisticsPageController::class, 'billing'])->middleware('web.permission:billing.view')->name('billing.monthly-invoices');
    Route::get('/billing/spot-invoices', [LogisticsPageController::class, 'billing'])->middleware('web.permission:billing.view')->name('billing.spot-invoices');
    Route::get('/billing/invoices', [LogisticsPageController::class, 'billing'])->middleware('web.permission:billing.view')->name('billing.invoices');
    Route::get('/billing/invoice-print', [LogisticsPageController::class, 'billing'])->middleware('web.permission:billing.view')->name('billing.invoice-print');
    Route::get('/billing/invoices/print-batch', [LogisticsPageController::class, 'printInvoices'])->middleware('web.permission:billing.view')->name('billing.invoices.print-batch');
    Route::get('/billing/invoices/{invoice}/print', [LogisticsPageController::class, 'printInvoice'])->middleware('web.permission:billing.view')->name('billing.invoices.print');
    Route::get('/billing/payment-confirmation', [LogisticsPageController::class, 'billing'])->middleware('web.permission:billing.view')->name('billing.payment-confirmation');
    Route::get('/billing/payment-reviews', [LogisticsPageController::class, 'billing'])->middleware('web.permission:billing.view')->name('billing.payment-reviews');
    Route::get('/billing/receivables', [LogisticsPageController::class, 'billing'])->middleware('web.permission:billing.view')->name('billing.receivables');
    Route::redirect('/sales-returns', '/sales-returns/register')->middleware('web.permission:sales_return.view')->name('sales-returns.index');
    Route::get('/sales-returns/register', [LogisticsPageController::class, 'salesReturns'])->middleware('web.permission:sales_return.view')->name('sales-returns.register');
    Route::get('/sales-returns/history', [LogisticsPageController::class, 'salesReturns'])->middleware('web.permission:sales_return.view')->name('sales-returns.history');
    Route::get('/settings', [AppSettingController::class, 'edit'])
        ->middleware('web.permission:role.manage')
        ->name('settings.index');
    Route::get('/tax', [TaxPageController::class, 'index'])
        ->middleware('web.permission:tax.view')
        ->name('tax.index');
    Route::get('/masters/customers', [CustomerMasterPageController::class, 'index'])
        ->middleware('web.permission:customer_master.view')
        ->name('masters.customers.index');
    Route::get('/masters/billing-cycles', [BillingCycleMasterPageController::class, 'index'])
        ->middleware('web.permission:billing_cycle_master.view')
        ->name('masters.billing-cycles.index');
    Route::get('/masters/products', [ProductMasterPageController::class, 'index'])
        ->middleware('web.permission:product_master.view')
        ->name('masters.products.index');
    Route::get('/masters/foundation/{master}', [FoundationMasterPageController::class, 'show'])
        ->name('masters.foundation.show');
    Route::put('/settings', [AppSettingController::class, 'update'])
        ->middleware('web.permission:role.manage')
        ->name('settings.update');
});
