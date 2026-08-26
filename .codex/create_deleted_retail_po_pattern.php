<?php

use App\Models\Retail\RetailPurchaseOrder;
use App\Models\Retail\RetailSupplier;
use App\Services\Retail\CancelRetailPurchaseOrderService;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$supplier = RetailSupplier::query()->where('supplier_code', 'BREWERY')->firstOrFail();
$no = 'CODX-P1-DELETED-'.now()->format('His');
$order = RetailPurchaseOrder::query()->create([
    'purchase_order_no' => $no,
    'retail_supplier_id' => $supplier->id,
    'supplier_type' => 'brewery',
    'order_route' => 'brewery_api',
    'status' => 'draft',
    'subtotal_amount' => 0,
    'tax_amount' => 0,
    'total_amount' => 0,
    'note' => 'P1 未送信削除パターン',
]);
$order->lines()->create([
    'description' => 'P1 未送信削除ダミー明細',
    'quantity' => 1,
    'unit_cost' => 0,
    'tax_amount' => 0,
    'line_amount' => 0,
]);

app(CancelRetailPurchaseOrderService::class)->deleteDraft($order);

echo json_encode([
    'pattern' => 'P1 未送信削除',
    'retail_order' => $no,
    'remaining_orders' => RetailPurchaseOrder::query()->where('purchase_order_no', $no)->count(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
