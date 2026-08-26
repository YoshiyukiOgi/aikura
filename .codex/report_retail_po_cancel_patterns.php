<?php

use App\Models\Retail\RetailPurchaseOrder;
use App\Models\SalesOrder;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orders = RetailPurchaseOrder::query()
    ->whereIn('purchase_order_no', ['RPO-20260813-0001', 'RPO-20260813-0002', 'RPO-20260813-0003'])
    ->orderBy('purchase_order_no')
    ->get();

foreach ($orders as $order) {
    $original = $order->brewery_sales_order_id ? SalesOrder::query()->find($order->brewery_sales_order_id) : null;
    $correction = $order->brewery_cancellation_sales_order_id ? SalesOrder::query()->with('lines')->find($order->brewery_cancellation_sales_order_id) : null;
    echo implode(' | ', [
        $order->purchase_order_no,
        'retail='.$order->status,
        'brewery_cancel='.$order->brewery_cancel_status,
        'original='.($original?->order_number ?? '-').'/'.($original?->status ?? '-'),
        'correction='.($correction?->order_number ?? '-').'/'.($correction?->lines->first()?->quantity ?? '-'),
        'error='.($order->brewery_cancel_error ?: '-'),
    ]).PHP_EOL;
}

echo 'deleted_check RPO-20260813-0001 count='.RetailPurchaseOrder::query()->where('purchase_order_no', 'RPO-20260813-0001')->count().PHP_EOL;
