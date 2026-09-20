<?php

use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BillingCycleMasterController;
use App\Http\Controllers\Api\V1\CustomerMasterController;
use App\Http\Controllers\Api\V1\FoundationMasterController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InventoryCountController;
use App\Http\Controllers\Api\V1\MonthlyClosingController;
use App\Http\Controllers\Api\V1\NonSalesStockOperationController;
use App\Http\Controllers\Api\V1\PriceReviewTaskController;
use App\Http\Controllers\Api\V1\PriceRuleController;
use App\Http\Controllers\Api\V1\ProductItemMasterController;
use App\Http\Controllers\Api\V1\ProductMasterController;
use App\Http\Controllers\Api\V1\SalesOrderController;
use App\Http\Controllers\Api\V1\SalesReturnController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ShipmentController;
use App\Http\Controllers\Api\V1\ShipmentInstructionController;
use App\Http\Controllers\Api\V1\ShipmentPickController;
use App\Http\Controllers\Api\V1\StockMovementController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\TaxController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/', [SystemController::class, 'index'])->name('index');
    Route::get('/routes', [SystemController::class, 'routes'])->name('routes');

    Route::middleware(['auth', 'permission:audit_log.view'])->group(function (): void {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    Route::middleware(['auth'])->group(function (): void {
        Route::middleware('permission:customer_master.view')->group(function (): void {
            Route::get('/masters/customers', [CustomerMasterController::class, 'index'])->name('masters.customers.index');
            Route::get('/masters/customers/{customer}', [CustomerMasterController::class, 'show'])->name('masters.customers.show');
        });
        Route::post('/masters/customers', [CustomerMasterController::class, 'store'])
            ->middleware('permission:customer_master.edit')
            ->name('masters.customers.store');
        Route::put('/masters/customers/{customer}', [CustomerMasterController::class, 'update'])
            ->middleware('permission:customer_master.edit')
            ->name('masters.customers.update');

        Route::middleware('permission:billing_cycle_master.view')->group(function (): void {
            Route::get('/masters/billing-cycles', [BillingCycleMasterController::class, 'index'])->name('masters.billing-cycles.index');
            Route::get('/masters/billing-cycles/{billingCycle}', [BillingCycleMasterController::class, 'show'])->name('masters.billing-cycles.show');
        });
        Route::post('/masters/billing-cycles', [BillingCycleMasterController::class, 'store'])
            ->middleware('permission:billing_cycle_master.edit')
            ->name('masters.billing-cycles.store');
        Route::put('/masters/billing-cycles/{billingCycle}', [BillingCycleMasterController::class, 'update'])
            ->middleware('permission:billing_cycle_master.edit')
            ->name('masters.billing-cycles.update');

        Route::middleware('permission:product_master.view')->group(function (): void {
            Route::get('/masters/product-families/export', [ProductMasterController::class, 'export'])->name('masters.product-families.export');
            Route::get('/masters/product-families', [ProductMasterController::class, 'index'])->name('masters.product-families.index');
            Route::get('/masters/product-families/{productFamily}', [ProductMasterController::class, 'show'])->name('masters.product-families.show');
        });
        Route::post('/masters/product-families', [ProductMasterController::class, 'store'])
            ->middleware('permission:product_master.edit')->name('masters.product-families.store');
        Route::put('/masters/product-families/{productFamily}', [ProductMasterController::class, 'update'])
            ->middleware('permission:product_master.edit')->name('masters.product-families.update');
        Route::post('/masters/product-families/{productFamily}/products', [ProductMasterController::class, 'storeVariant'])
            ->middleware('permission:product_master.edit')->name('masters.product-families.products.store');
        Route::put('/masters/product-families/{productFamily}/products/{product}', [ProductMasterController::class, 'updateVariant'])
            ->middleware('permission:product_master.edit')->name('masters.product-families.products.update');

        Route::middleware('permission:product_master.view')->group(function (): void {
            Route::get('/masters/products/export', [ProductItemMasterController::class, 'export'])->name('masters.products.export');
            Route::get('/masters/products', [ProductItemMasterController::class, 'index'])->name('masters.products.index');
            Route::get('/masters/products/{product}', [ProductItemMasterController::class, 'show'])->name('masters.products.show');
        });
        Route::post('/masters/products', [ProductItemMasterController::class, 'store'])
            ->middleware('permission:product_master.edit')->name('masters.products.store');
        Route::put('/masters/products/{product}', [ProductItemMasterController::class, 'update'])
            ->middleware('permission:product_master.edit')->name('masters.products.update');
        Route::post('/masters/products/{product}/price-revisions', [ProductItemMasterController::class, 'storePriceRevision'])
            ->middleware('permission:price.change')->name('masters.products.price-revisions.store');
        Route::post('/masters/products/{product}/price-rules/{priceRule}/deactivate', [ProductItemMasterController::class, 'deactivatePriceRule'])
            ->middleware('permission:price.change')->name('masters.products.price-rules.deactivate');

        Route::get('/masters/foundation/{master}', [FoundationMasterController::class, 'index'])
            ->name('masters.foundation.index');
        Route::get('/masters/foundation/{master}/{id}', [FoundationMasterController::class, 'show'])
            ->whereNumber('id')
            ->name('masters.foundation.show');
        Route::post('/masters/foundation/{master}', [FoundationMasterController::class, 'store'])
            ->name('masters.foundation.store');
        Route::put('/masters/foundation/{master}/{id}', [FoundationMasterController::class, 'update'])
            ->whereNumber('id')
            ->name('masters.foundation.update');

        Route::middleware('permission:sales_order.view')->group(function (): void {
            Route::get('/sales-orders', [SalesOrderController::class, 'index'])->name('sales-orders.index');
            Route::get('/sales-orders/{sales_order}', [SalesOrderController::class, 'show'])->name('sales-orders.show');
        });

        Route::post('/sales-orders', [SalesOrderController::class, 'store'])
            ->middleware('permission:sales_order.create')
            ->name('sales-orders.store');

        Route::put('/sales-orders/{sales_order}', [SalesOrderController::class, 'update'])
            ->middleware('permission:sales_order.update')
            ->name('sales-orders.update');

        Route::post('/sales-orders/{sales_order}/release-to-shipping', [SalesOrderController::class, 'releaseToShipping'])
            ->middleware('permission:sales_order.update')
            ->name('sales-orders.release-to-shipping');

        Route::post('/sales-orders/{sales_order}/defer-shipment-instruction', [SalesOrderController::class, 'deferShipmentInstruction'])
            ->middleware('permission:sales_order.update')
            ->name('sales-orders.defer-shipment-instruction');

        Route::post('/sales-orders/{sales_order}/resume-from-shipment-instruction', [SalesOrderController::class, 'resumeFromShipmentInstruction'])
            ->middleware('permission:sales_order.update')
            ->name('sales-orders.resume-from-shipment-instruction');

        Route::post('/sales-orders/{sales_order}/price', [SalesOrderController::class, 'price'])
            ->middleware('permission:price.change')
            ->name('sales-orders.price');

        Route::patch('/sales-orders/{sales_order}/lines/{sales_order_line}/price', [SalesOrderController::class, 'overrideLinePrice'])
            ->middleware('permission:price.change')
            ->name('sales-orders.lines.price');

        Route::post('/sales-orders/{sales_order}/lines/{sales_order_line}/reprice', [SalesOrderController::class, 'reapplyLinePrice'])
            ->middleware('permission:price.change')
            ->name('sales-orders.lines.reprice');

        Route::post('/sales-orders/{sales_order}/cancel', [SalesOrderController::class, 'cancel'])
            ->middleware('permission:sales_order.cancel')
            ->name('sales-orders.cancel');

        Route::put('/price-rules/{price_rule}', [PriceRuleController::class, 'update'])
            ->middleware('permission:price.change')
            ->name('price-rules.update');

        Route::post('/price-review-tasks/notify-selection', [PriceReviewTaskController::class, 'notifyForSelection'])
            ->middleware('permission:sales_order.view')
            ->name('price-review-tasks.notify-selection');

        Route::post('/price-review-tasks/{price_review_task}/keep', [PriceReviewTaskController::class, 'keep'])
            ->middleware('permission:price.change')
            ->name('price-review-tasks.keep');

        Route::post('/price-review-tasks/{price_review_task}/updated', [PriceReviewTaskController::class, 'updated'])
            ->middleware('permission:price.change')
            ->name('price-review-tasks.updated');

        Route::middleware('permission:shipment_instruction.view')->group(function (): void {
            Route::get('/shipment-instructions', [ShipmentInstructionController::class, 'index'])->name('shipment-instructions.index');
            Route::get('/shipment-instructions/{shipment_instruction}', [ShipmentInstructionController::class, 'show'])->name('shipment-instructions.show');
        });

        Route::post('/shipment-instructions', [ShipmentInstructionController::class, 'store'])
            ->middleware('permission:shipment_instruction.create')
            ->name('shipment-instructions.store');

        Route::post('/shipment-instructions/{shipment_instruction}/issue-shipment-document', [ShipmentController::class, 'issueFromInstruction'])
            ->middleware('permission:shipment.create')
            ->name('shipment-instructions.issue-shipment-document');

        Route::post('/shipment-instructions/{shipment_instruction}/cancel-shipping-flow', [ShipmentInstructionController::class, 'cancelShippingFlow'])
            ->middleware('permission:shipment.cancel')
            ->name('shipment-instructions.cancel-shipping-flow');

        Route::post('/shipment-instructions/{shipment_instruction}/cancel', [ShipmentInstructionController::class, 'cancel'])
            ->middleware('permission:shipment_instruction.cancel')
            ->name('shipment-instructions.cancel');

        Route::middleware('permission:shipment_pick.view')->group(function (): void {
            Route::get('/shipment-picks', [ShipmentPickController::class, 'index'])->name('shipment-picks.index');
            Route::get('/shipment-picks/{shipment_pick}', [ShipmentPickController::class, 'show'])->name('shipment-picks.show');
            Route::get('/shipment-instructions/{shipment_instruction}/lines/{shipment_instruction_line}/lots', [ShipmentPickController::class, 'lineLots'])->name('shipment-instructions.lines.lots');
        });

        Route::post('/shipment-picks', [ShipmentPickController::class, 'store'])
            ->middleware('permission:shipment_pick.create')
            ->name('shipment-picks.store');

        Route::put('/shipment-instructions/{shipment_instruction}/lines/{shipment_instruction_line}/lots', [ShipmentPickController::class, 'saveLineLots'])
            ->middleware('permission:shipment_pick.create')
            ->name('shipment-instructions.lines.lots.save');

        Route::post('/shipment-picks/{shipment_pick}/cancel', [ShipmentPickController::class, 'cancel'])
            ->middleware('permission:shipment_pick.cancel')
            ->name('shipment-picks.cancel');

        Route::middleware('permission:shipment.view')->group(function (): void {
            Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
            Route::get('/shipments-history', [ShipmentController::class, 'history'])->name('shipments.history');
            Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');
        });

        Route::post('/shipments', [ShipmentController::class, 'store'])
            ->middleware('permission:shipment.create')
            ->name('shipments.store');

        Route::post('/shipments/{shipment}/price', [ShipmentController::class, 'price'])
            ->middleware('permission:shipment.price')
            ->name('shipments.price');

        Route::post('/shipments/{shipment}/issue-document', [ShipmentController::class, 'issueDocument'])
            ->middleware('permission:shipment.create')
            ->name('shipments.issue-document');

        Route::post('/shipments/{shipment}/print-and-confirm', [ShipmentController::class, 'printAndConfirm'])
            ->middleware('permission:shipment.confirm')
            ->name('shipments.print-and-confirm');

        Route::post('/shipments/{shipment}/confirm', [ShipmentController::class, 'confirm'])
            ->middleware('permission:shipment.confirm')
            ->name('shipments.confirm');

        Route::post('/shipments/{shipment}/cancel', [ShipmentController::class, 'cancel'])
            ->middleware('permission:shipment.cancel')
            ->name('shipments.cancel');

        Route::middleware('permission:inventory.view')->group(function (): void {
            Route::get('/inventory/stock', [InventoryController::class, 'stock'])->name('inventory.stock');
            Route::get('/inventory/lot-stock', [InventoryController::class, 'lotStock'])->name('inventory.lot-stock');
            Route::get('/inventory/lot-stock-as-of', [InventoryController::class, 'lotStockAsOf'])->name('inventory.lot-stock-as-of');
            Route::get('/inventory/lots', [InventoryController::class, 'lots'])->name('inventory.lots.index');
            Route::get('/picking/products/{product}/lot-candidate-summary', [InventoryController::class, 'productLotCandidateSummary'])->name('picking.products.lot-candidate-summary');
            Route::get('/inventory/counts', [InventoryCountController::class, 'index'])->name('inventory.counts.index');
            Route::get('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'show'])->name('inventory.counts.show');
            Route::get('/inventory/movements', [StockMovementController::class, 'index'])->name('inventory.movements.index');
        });

        Route::middleware('permission:stock.adjust')->group(function (): void {
            Route::post('/inventory/counts', [InventoryCountController::class, 'store'])->name('inventory.counts.store');
            Route::put('/inventory/counts/{inventoryCount}', [InventoryCountController::class, 'update'])->name('inventory.counts.update');
            Route::post('/inventory/counts/{inventoryCount}/confirm', [InventoryCountController::class, 'confirm'])->name('inventory.counts.confirm');
            Route::post('/inventory/movements/{stockMovement}/correct', [StockMovementController::class, 'correct'])->name('inventory.movements.correct');
            Route::put('/inventory/lots/{productionLot}', [InventoryController::class, 'updateLot'])->name('inventory.lots.update');
        });

        Route::post('/inventory/allocate', [InventoryController::class, 'allocate'])
            ->middleware('permission:inventory.allocate')
            ->name('inventory.allocate');

        Route::middleware('permission:billing.view')->group(function (): void {
            Route::get('/billing/billable-shipments', [BillingController::class, 'billableShipments'])->name('billing.billable-shipments');
            Route::get('/billing/monthly-targets', [BillingController::class, 'monthlyBillingTargets'])->name('billing.monthly-targets');
            Route::get('/billing/invoices', [BillingController::class, 'invoices'])->name('billing.invoices');
            Route::get('/billing/invoices/{invoice}', [BillingController::class, 'invoice'])->name('billing.invoices.show');
            Route::get('/billing/payment-schedules', [BillingController::class, 'paymentSchedules'])->name('billing.payment-schedules');
            Route::get('/billing/payments', [BillingController::class, 'payments'])->name('billing.payments');
            Route::get('/billing/receivables', [BillingController::class, 'receivables'])->name('billing.receivables');
            Route::get('/billing/customer-monthly-statements', [BillingController::class, 'customerMonthlyStatements'])->name('billing.customer-monthly-statements');
        });

        Route::post('/billing/invoices', [BillingController::class, 'createInvoice'])
            ->middleware('permission:billing.invoice.create')
            ->name('billing.invoices.store');

        Route::post('/billing/closing-invoices', [BillingController::class, 'createClosingInvoice'])
            ->middleware('permission:billing.invoice.create')
            ->name('billing.closing-invoices.store');

        Route::post('/billing/invoices/{invoice}/confirm', [BillingController::class, 'confirmInvoice'])
            ->middleware('permission:billing.invoice.confirm')
            ->name('billing.invoices.confirm');

        Route::post('/billing/invoices/{invoice}/cancel', [BillingController::class, 'cancelInvoice'])
            ->middleware('permission:billing.invoice.cancel')
            ->name('billing.invoices.cancel');

        Route::post('/billing/payment-schedules', [BillingController::class, 'createPaymentSchedule'])
            ->middleware('permission:billing.payment_schedule.create')
            ->name('billing.payment-schedules.store');

        Route::post('/billing/payments', [BillingController::class, 'registerPayment'])
            ->middleware('permission:billing.payment.create')
            ->name('billing.payments.store');

        Route::post('/billing/payments/{payment}/cancel', [BillingController::class, 'cancelPayment'])
            ->middleware('permission:billing.payment.cancel')
            ->name('billing.payments.cancel');

        Route::middleware('permission:sales_return.view')->group(function (): void {
            Route::get('/sales-returns', [SalesReturnController::class, 'index'])->name('sales-returns.index');
            Route::get('/sales-returns/{sales_return_header}', [SalesReturnController::class, 'show'])->name('sales-returns.show');
            Route::get('/sales-return-source-lines/{shipment_line}/lots', [SalesReturnController::class, 'sourceLineLots'])->name('sales-returns.source-lines.lots');
        });

        Route::post('/sales-returns', [SalesReturnController::class, 'store'])
            ->middleware('permission:sales_return.create')
            ->name('sales-returns.store');

        Route::post('/sales-returns/{sales_return_header}/cancel', [SalesReturnController::class, 'cancel'])
            ->middleware('permission:sales_return.create')
            ->name('sales-returns.cancel');

        Route::middleware('permission:non_sales_stock.view')->group(function (): void {
            Route::get('/non-sales-stock-operations', [NonSalesStockOperationController::class, 'index'])->name('non-sales-stock-operations.index');
            Route::get('/non-sales-stock-operations/{non_sales_stock_operation_header}', [NonSalesStockOperationController::class, 'show'])->name('non-sales-stock-operations.show');
        });

        Route::post('/non-sales-stock-operations', [NonSalesStockOperationController::class, 'store'])
            ->middleware('permission:non_sales_stock.create')
            ->name('non-sales-stock-operations.store');

        Route::post('/non-sales-stock-operations/repackaging-alcohol-check', [NonSalesStockOperationController::class, 'checkRepackagingAlcohol'])
            ->middleware('permission:non_sales_stock.create')
            ->name('non-sales-stock-operations.repackaging-alcohol-check');

        Route::put('/non-sales-stock-operations/{non_sales_stock_operation_header}', [NonSalesStockOperationController::class, 'update'])
            ->middleware('permission:non_sales_stock.create')
            ->name('non-sales-stock-operations.update');

        Route::post('/non-sales-stock-operations/{non_sales_stock_operation_header}/cancel', [NonSalesStockOperationController::class, 'cancel'])
            ->middleware('permission:non_sales_stock.create')
            ->name('non-sales-stock-operations.cancel');

        Route::middleware('permission:tax.view')->group(function (): void {
            Route::get('/tax/liquor-monthly-filings', [TaxController::class, 'liquorFilings'])->name('tax.liquor-monthly-filings');
            Route::get('/tax/liquor-monthly-filings/{filing}', [TaxController::class, 'liquorFiling'])->name('tax.liquor-monthly-filings.show');
            Route::get('/tax/liquor-settings', [TaxController::class, 'liquorSettings'])->name('tax.liquor-settings');
            Route::get('/tax/report-exports/{reportExport}/download', [TaxController::class, 'downloadLiquorFilingExport'])->name('tax.report-exports.download');
            Route::get('/tax/shipment-liquor-tax-evidences/{evidence}/document', [TaxController::class, 'downloadShipmentLiquorTaxEvidenceDocument'])->name('tax.shipment-liquor-tax-evidences.document');
            Route::get('/tax/consumption-monthly-filings', [TaxController::class, 'consumptionFilings'])->name('tax.consumption-monthly-filings');
            Route::get('/tax/consumption-monthly-filings/{filing}', [TaxController::class, 'consumptionFiling'])->name('tax.consumption-monthly-filings.show');
        });

        Route::post('/tax/liquor-monthly-filings', [TaxController::class, 'createLiquorFiling'])
            ->middleware('permission:tax.liquor_filing.create')
            ->name('tax.liquor-monthly-filings.store');

        Route::post('/tax/liquor-settings/relief-periods', [TaxController::class, 'createLiquorReliefSetting'])
            ->middleware('permission:tax.liquor_settings.manage')
            ->name('tax.liquor-settings.relief-periods.store');

        Route::put('/tax/liquor-settings/adjustment-thresholds', [TaxController::class, 'updateLiquorAdjustmentSetting'])
            ->middleware('permission:tax.liquor_settings.manage')
            ->name('tax.liquor-settings.adjustment-thresholds.update');

        Route::post('/tax/liquor-monthly-filings/{filing}/confirm', [TaxController::class, 'confirmLiquorFiling'])
            ->middleware('permission:tax.liquor_filing.confirm')
            ->name('tax.liquor-monthly-filings.confirm');

        Route::post('/tax/liquor-monthly-filings/{filing}/reopen', [TaxController::class, 'reopenLiquorFiling'])
            ->middleware('permission:tax.liquor_filing.confirm')
            ->name('tax.liquor-monthly-filings.reopen');

        Route::post('/tax/liquor-monthly-filings/{filing}/adjustments', [TaxController::class, 'createLiquorAdjustment'])
            ->middleware('permission:tax.liquor_filing.create')
            ->name('tax.liquor-monthly-filings.adjustments.store');

        Route::post('/tax/liquor-monthly-filings/{filing}/adjustments/{adjustment}/void', [TaxController::class, 'voidLiquorAdjustment'])
            ->middleware('permission:tax.liquor_filing.create')
            ->name('tax.liquor-monthly-filings.adjustments.void');

        Route::post('/tax/liquor-monthly-filings/{filing}/exports', [TaxController::class, 'createLiquorFilingExport'])
            ->middleware('permission:tax.liquor_filing.confirm')
            ->name('tax.liquor-monthly-filings.exports.store');

        Route::put('/tax/shipments/{shipment}/liquor-tax-evidence', [TaxController::class, 'recordShipmentLiquorTaxEvidence'])
            ->middleware('permission:tax.liquor_filing.create')
            ->name('tax.shipments.liquor-tax-evidence.update');

        Route::put('/tax/sales-return-lines/{line}/liquor-tax-treatment', [TaxController::class, 'reviewSalesReturnLiquorTax'])
            ->middleware('permission:tax.liquor_filing.create')
            ->name('tax.sales-return-lines.liquor-tax-treatment.update');

        Route::post('/tax/consumption-monthly-filings', [TaxController::class, 'createConsumptionFiling'])
            ->middleware('permission:tax.consumption_filing.create')
            ->name('tax.consumption-monthly-filings.store');

        Route::post('/tax/consumption-monthly-filings/{filing}/confirm', [TaxController::class, 'confirmConsumptionFiling'])
            ->middleware('permission:tax.consumption_filing.confirm')
            ->name('tax.consumption-monthly-filings.confirm');

        Route::middleware('permission:monthly_closing.view')->group(function (): void {
            Route::get('/monthly-closing/stock-balances', [MonthlyClosingController::class, 'stockBalances'])->name('monthly-closing.stock-balances');
            Route::get('/monthly-closing/receivable-balances', [MonthlyClosingController::class, 'receivableBalances'])->name('monthly-closing.receivable-balances');
            Route::get('/monthly-closing/internal-balances', [MonthlyClosingController::class, 'internalBalances'])->name('monthly-closing.internal-balances');
        });

        Route::middleware('permission:monthly_closing.execute')->group(function (): void {
            Route::post('/monthly-closing/stock-balances', [MonthlyClosingController::class, 'createStockBalances'])->name('monthly-closing.stock-balances.store');
            Route::post('/monthly-closing/stock-balances/{year}/{month}/confirm', [MonthlyClosingController::class, 'confirmStockBalances'])->name('monthly-closing.stock-balances.confirm');
            Route::post('/monthly-closing/receivable-balances', [MonthlyClosingController::class, 'createReceivableBalances'])->name('monthly-closing.receivable-balances.store');
            Route::post('/monthly-closing/receivable-balances/{year}/{month}/confirm', [MonthlyClosingController::class, 'confirmReceivableBalances'])->name('monthly-closing.receivable-balances.confirm');
            Route::post('/monthly-closing/receivable-balances/{year}/{month}/close', [MonthlyClosingController::class, 'closeReceivableBalances'])->name('monthly-closing.receivable-balances.close');
            Route::post('/monthly-closing/internal-balances', [MonthlyClosingController::class, 'createInternalBalances'])->name('monthly-closing.internal-balances.store');
            Route::post('/monthly-closing/internal-balances/{year}/{month}/confirm', [MonthlyClosingController::class, 'confirmInternalBalances'])->name('monthly-closing.internal-balances.confirm');
            Route::post('/monthly-closing/internal-balances/{year}/{month}/close', [MonthlyClosingController::class, 'closeInternalBalances'])->name('monthly-closing.internal-balances.close');
        });

        Route::middleware('permission:search.view')->group(function (): void {
            Route::get('/search', [SearchController::class, 'index'])->name('search.index');
            Route::get('/search/transactions', [SearchController::class, 'transactions'])->name('search.transactions');
            Route::get('/search/statuses', [SearchController::class, 'statuses'])->name('search.statuses');
            Route::get('/search/relations', [SearchController::class, 'relations'])->name('search.relations');
            Route::get('/search/pending', [SearchController::class, 'pending'])->name('search.pending');
            Route::get('/search/review-required', [SearchController::class, 'reviewRequired'])->name('search.review-required');
            Route::get('/search/closing-targets', [SearchController::class, 'closingTargets'])->name('search.closing-targets');
            Route::get('/search/audit-logs', [SearchController::class, 'auditLogs'])->name('search.audit-logs');
        });

        Route::middleware('permission:approval.view')->group(function (): void {
            Route::get('/approval-requests', [ApprovalController::class, 'index'])->name('approval-requests.index');
            Route::get('/approval-requests/{approval_request}', [ApprovalController::class, 'show'])->name('approval-requests.show');
        });

        Route::post('/approval-requests', [ApprovalController::class, 'store'])
            ->middleware('permission:approval.request')
            ->name('approval-requests.store');

        Route::post('/approval-requests/{approval_request}/resubmit', [ApprovalController::class, 'resubmit'])
            ->middleware('permission:approval.request')
            ->name('approval-requests.resubmit');

        Route::middleware('permission:approval.approve')->group(function (): void {
            Route::post('/approval-requests/{approval_request}/approve', [ApprovalController::class, 'approve'])->name('approval-requests.approve');
            Route::post('/approval-requests/{approval_request}/reject', [ApprovalController::class, 'reject'])->name('approval-requests.reject');
            Route::post('/approval-requests/{approval_request}/return', [ApprovalController::class, 'returnForCorrection'])->name('approval-requests.return');
            Route::post('/approval-requests/{approval_request}/consume', [ApprovalController::class, 'consume'])->name('approval-requests.consume');
        });
    });
});
