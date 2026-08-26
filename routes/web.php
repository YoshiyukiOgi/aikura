<?php

use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingCycleMasterPageController;
use App\Http\Controllers\CustomerMasterPageController;
use App\Http\Controllers\FoundationMasterPageController;
use App\Http\Controllers\InventoryPageController;
use App\Http\Controllers\LogisticsPageController;
use App\Http\Controllers\ProductMasterPageController;
use App\Http\Controllers\Retail\RetailBreweryProductImportController;
use App\Http\Controllers\Retail\RetailCompanyController;
use App\Http\Controllers\Retail\RetailCustomerController;
use App\Http\Controllers\Retail\RetailDeliveryController;
use App\Http\Controllers\Retail\RetailInvoiceController;
use App\Http\Controllers\Retail\RetailPaymentController;
use App\Http\Controllers\Retail\RetailProductController;
use App\Http\Controllers\Retail\RetailPurchaseOrderController;
use App\Http\Controllers\Retail\RetailSaleController;
use App\Http\Controllers\Retail\RetailSettingController;
use App\Http\Controllers\SalesOrderPageController;
use App\Http\Controllers\TaxPageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/sales-orders');
Route::view('/concept/retail-architecture', 'concepts.retail-architecture');
Route::view('/concept/retail-top', 'concepts.retail-top');
Route::view('/concept/retail-sales', 'concepts.retail-sales');
Route::view('/concept/retail-pos', 'concepts.retail-pos');
Route::view('/concept/retail-settings', 'concepts.retail-settings');

$retailRoutes = static function (): void {
    Route::get('/', fn () => redirect()->route('retail.pos'))->name('index');
    Route::get('/companies', [RetailCompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/manage', fn () => redirect()->route('retail.system.manage'))->name('companies.manage');
    Route::get('/system-management', [RetailCompanyController::class, 'manage'])->name('system.manage');
    Route::put('/system-management', [RetailCompanyController::class, 'updateSystem'])->name('system.update');
    Route::post('/companies', [RetailCompanyController::class, 'store'])->name('companies.store');
    Route::post('/companies/select', [RetailCompanyController::class, 'select'])->name('companies.select');
    Route::delete('/companies/{company}', [RetailCompanyController::class, 'destroy'])->name('companies.destroy');
    Route::view('/sales-menu', 'concepts.retail-sales')->name('sales-menu');
    Route::get('/pos', [RetailSaleController::class, 'create'])->name('pos');
    Route::get('/sales', [RetailSaleController::class, 'index'])->name('sales.index');
    Route::post('/sales', [RetailSaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{retailSale}', [RetailSaleController::class, 'show'])->name('sales.show');
    Route::put('/sales/{retailSale}', [RetailSaleController::class, 'revise'])->name('sales.revise');
    Route::post('/sales/{retailSale}/cancel', [RetailSaleController::class, 'cancel'])->name('sales.cancel');
    Route::post('/sales/{retailSale}/credit-note', [RetailSaleController::class, 'creditNote'])->name('sales.credit-note');
    Route::get('/deliveries', [RetailDeliveryController::class, 'index'])->name('deliveries.index');
    Route::post('/deliveries', [RetailDeliveryController::class, 'store'])->name('deliveries.store');
    Route::get('/deliveries/{retailDelivery}', [RetailDeliveryController::class, 'show'])->name('deliveries.show');
    Route::post('/deliveries/{retailDelivery}/cancel', [RetailDeliveryController::class, 'cancel'])->name('deliveries.cancel');
    Route::get('/invoices', [RetailInvoiceController::class, 'index'])->name('invoices.index');
    Route::post('/invoices', [RetailInvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{retailInvoice}', [RetailInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{retailInvoice}/cancel', [RetailInvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::get('/payments', [RetailPaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [RetailPaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{retailPayment}', [RetailPaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{retailPayment}/refund', [RetailPaymentController::class, 'refund'])->name('payments.refund');
    Route::get('/purchase-orders', [RetailPurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::post('/purchase-orders/suggestions', [RetailPurchaseOrderController::class, 'createSuggestions'])->name('purchase-orders.suggestions');
    Route::post('/purchase-orders/{retailPurchaseOrder}/send-brewery', [RetailPurchaseOrderController::class, 'sendBrewery'])->name('purchase-orders.send-brewery');
    Route::delete('/purchase-orders/{retailPurchaseOrder}', [RetailPurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy');
    Route::post('/purchase-orders/{retailPurchaseOrder}/cancel', [RetailPurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::get('/settings', [RetailSettingController::class, 'index'])->name('settings');
    Route::put('/settings', [RetailSettingController::class, 'update'])->name('settings.update');
    Route::get('/customers', [RetailCustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [RetailCustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{retailCustomer}', [RetailCustomerController::class, 'update'])->name('customers.update');
    Route::get('/products', [RetailProductController::class, 'index'])->name('products.index');
    Route::post('/products', [RetailProductController::class, 'store'])->name('products.store');
    Route::put('/products/{retailProduct}', [RetailProductController::class, 'update'])->name('products.update');
    Route::get('/products/import', [RetailBreweryProductImportController::class, 'index'])->name('products.import');
    Route::put('/products/import/selections', [RetailBreweryProductImportController::class, 'updateSelections'])->name('products.import.selections.update');
    Route::post('/products/import/bulk', [RetailBreweryProductImportController::class, 'bulkStore'])->name('products.import.bulk');
    Route::post('/products/import/price-sync-setting', [RetailBreweryProductImportController::class, 'updatePriceSyncSetting'])->name('products.import.price-sync-setting');
    Route::post('/products/import/price-changes/detect', [RetailBreweryProductImportController::class, 'detectPriceChanges'])->name('products.import.price-changes.detect');
    Route::post('/products/import/price-changes/{candidate}/apply', [RetailBreweryProductImportController::class, 'applyPriceChange'])->name('products.import.price-changes.apply');
    Route::post('/products/import/{product}', [RetailBreweryProductImportController::class, 'store'])->name('products.import.store');
};

$retailRouteRegistrar = Route::name('retail.')
    ->prefix(config('retail.route_prefix', 'retail'))
    ->middleware('auth');

if (config('retail.domain')) {
    $retailRouteRegistrar->domain(config('retail.domain'));
}

$retailRouteRegistrar->group($retailRoutes);

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::get('/retail-login', [AuthController::class, 'createRetail'])->name('retail.login');
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
    Route::get('/billing/customer-monthly-statements/print', [LogisticsPageController::class, 'printCustomerMonthlyStatements'])->middleware('web.permission:billing.view')->name('billing.customer-monthly-statements.print');
    Route::get('/billing/payments/print', [LogisticsPageController::class, 'printPayments'])->middleware('web.permission:billing.view')->name('billing.payments.print');
    Route::get('/billing/payment-entry', [LogisticsPageController::class, 'billing'])->middleware('web.permission:billing.view')->name('billing.payment-entry');
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
