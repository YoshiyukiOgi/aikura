<?php

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\InvoiceLine;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Retail\RetailCompany;
use App\Models\Retail\RetailCompanySetting;
use App\Models\Retail\RetailInventoryStock;
use App\Models\Retail\RetailProduct;
use App\Models\Retail\RetailPurchaseOrder;
use App\Models\Retail\RetailSupplier;
use App\Models\Retail\RetailSystemSetting;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\ShipmentInstruction;
use App\Models\ShipmentInstructionLine;
use App\Models\ShipmentLine;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Retail\CancelRetailPurchaseOrderService;
use App\Services\Retail\CreateRetailPurchaseOrderSuggestionsService;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$prefix = 'CODX-CANCEL-'.now()->format('His');

$role = Role::query()->firstOrCreate(
    ['code' => 'codex_demo_admin'],
    ['name' => 'Codex Demo Admin', 'is_active' => true],
);
foreach (['sales_order.view', 'sales_order.create', 'sales_order.update', 'sales_order.cancel', 'price.change'] as $code) {
    $permission = Permission::query()->firstOrCreate(
        ['code' => $code],
        ['name' => $code, 'description' => $code, 'is_active' => true],
    );
    $role->permissions()->syncWithoutDetaching([$permission->id]);
}
$user = User::query()->updateOrCreate(
    ['email' => 'codex-demo@example.com'],
    ['name' => 'Codex Demo', 'password' => Hash::make('password'), 'is_active' => true],
);
$user->roles()->syncWithoutDetaching([$role->id]);

RetailCompany::query()->updateOrCreate(
    ['company_key' => 'maru'],
    ['name' => '○テスト小売', 'description' => '取消パターン確認用', 'is_active' => true, 'sort_order' => 1],
);
RetailCompanySetting::query()->updateOrCreate(
    ['company_key' => 'maru'],
    [
        'sale_mode' => 'mixed',
        'inventory_sales_policy' => 'strict_stock',
        'delivery_note_policy' => 'on_demand',
        'invoice_policy' => 'monthly_credit',
        'brewery_procurement_policy' => 'auto_order',
        'external_procurement_policy' => 'supplier_order',
    ],
);
RetailSystemSetting::query()->updateOrCreate(
    ['id' => 1],
    ['system_name' => '小売販売システム'],
);

NumberSequence::query()->firstOrCreate(
    ['code' => 'sales_order'],
    ['name' => '受注番号', 'prefix' => 'SO-{YYYY}{MM}{DD}-', 'current_number' => 0, 'padding_length' => 4, 'reset_type' => 'none', 'is_active' => true],
);
NumberSequence::query()->firstOrCreate(
    ['code' => 'shipment_instruction'],
    ['name' => '出荷指示番号', 'prefix' => 'SI-{YYYY}{MM}{DD}-', 'current_number' => 0, 'padding_length' => 4, 'reset_type' => 'none', 'is_active' => true],
);

$transaction = TransactionCategory::query()->firstOrCreate(
    ['code' => 'codex_retail_auto_order'],
    ['name' => 'Codex小売自動発注', 'is_active' => true],
);
$settlement = SettlementReceivableCategory::query()->firstOrCreate(
    ['code' => 'codex_retail_accounts_receivable'],
    ['name' => 'Codex小売売掛', 'is_active' => true],
);
$billingCycle = BillingCycle::query()->firstOrCreate(
    ['code' => 'codex_retail_monthly_end'],
    ['name' => 'Codex小売月末締め', 'closing_day' => 31, 'is_active' => true],
);
$unit = Unit::query()->firstOrCreate(
    ['code' => 'codex_bottle'],
    ['name' => '本', 'symbol' => '本', 'unit_type' => 'count', 'is_active' => true],
);
$customer = Customer::query()->updateOrCreate(
    ['customer_code' => 'CODX-RETAIL-CUST'],
    [
        'name' => 'Codex小売会社',
        'transaction_category_id' => $transaction->id,
        'settlement_receivable_category_id' => $settlement->id,
        'billing_cycle_id' => $billingCycle->id,
        'is_active' => true,
    ],
);
$supplier = RetailSupplier::query()->updateOrCreate(
    ['supplier_code' => 'BREWERY'],
    [
        'name' => '蔵販売業務システム',
        'supplier_type' => 'brewery',
        'ordering_method' => 'api',
        'brewery_partner_id' => $customer->id,
        'is_active' => true,
    ],
);
$priceList = PriceList::query()->firstOrCreate(
    ['code' => 'codex_retail_auto_order_price'],
    ['name' => 'Codex小売自動発注価格', 'price_type' => 'wholesale', 'is_active' => true],
);

$suggestions = app(CreateRetailPurchaseOrderSuggestionsService::class);
$canceller = app(CancelRetailPurchaseOrderService::class);
$rows = [];

$makeProduct = function (string $code, string $name) use ($unit, $priceList, $transaction, $supplier): RetailProduct {
    $breweryProduct = Product::query()->updateOrCreate(
        ['product_code' => $code.'-BR'],
        [
            'product_type' => 'sake',
            'name' => $name.' 蔵商品',
            'display_name' => $name.' 蔵商品 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_sales_available' => true,
            'is_active' => true,
        ],
    );
    PriceRule::query()->updateOrCreate(
        ['price_list_id' => $priceList->id, 'product_id' => $breweryProduct->id, 'transaction_category_id' => $transaction->id, 'unit_id' => $unit->id],
        ['unit_price' => '1500.0000', 'currency' => 'JPY', 'priority' => 200, 'effective_from' => '2026-01-01', 'rounding_method' => 'round', 'is_active' => true],
    );
    $retailProduct = RetailProduct::query()->updateOrCreate(
        ['product_code' => $code],
        [
            'name' => $name,
            'procurement_source' => 'brewery',
            'retail_supplier_id' => $supplier->id,
            'brewery_product_id' => $breweryProduct->id,
            'cost_price' => 1000,
            'selling_price' => 1800,
            'tax_rate' => 0.1000,
            'stock_unit' => '本',
            'reorder_point' => 5,
            'reorder_quantity' => 12,
            'is_active' => true,
        ],
    );
    RetailInventoryStock::query()->updateOrCreate(
        ['retail_product_id' => $retailProduct->id],
        ['quantity' => 0],
    );

    return $retailProduct;
};

$makeDraftOrder = function (string $pattern, string $label) use ($makeProduct, $suggestions, $supplier): RetailPurchaseOrder {
    RetailProduct::query()
        ->where('retail_supplier_id', $supplier->id)
        ->where('product_code', 'like', 'CODX-CANCEL-%')
        ->update(['is_active' => false]);
    $product = $makeProduct($pattern, $label);
    $product->update(['is_active' => true]);
    $orders = $suggestions->create();

    return $orders->firstWhere('retail_supplier_id', $supplier->id) ?? RetailPurchaseOrder::query()->where('retail_supplier_id', $supplier->id)->latest('id')->firstOrFail();
};

$p1 = $makeDraftOrder($prefix.'-P1', 'P1 未送信削除');
$p1LineId = $p1->lines()->firstOrFail()->id;
$p1No = $p1->purchase_order_no;
$p1->lines()->delete();
$p1->delete();
$rows[] = ['pattern' => 'P1 未送信削除', 'retail_order' => $p1No, 'retail_status' => 'deleted', 'brewery_status' => '-', 'correction_order' => '-'];

$p2 = $makeDraftOrder($prefix.'-P2', 'P2 未出荷取消');
$suggestions->sendToBrewery($p2);
$p2->refresh();
$p2SalesOrderId = $p2->brewery_sales_order_id;
$canceller->cancel($p2, 'P2 未出荷なので通常取消');
$p2->refresh();
$p2Sales = SalesOrder::query()->find($p2SalesOrderId);
$rows[] = ['pattern' => 'P2 未出荷取消', 'retail_order' => $p2->purchase_order_no, 'retail_status' => $p2->status.' / '.$p2->brewery_cancel_status, 'brewery_status' => $p2Sales?->order_number.' / '.$p2Sales?->status, 'correction_order' => '-'];

$p3 = $makeDraftOrder($prefix.'-P3', 'P3 出荷指示済訂正');
$suggestions->sendToBrewery($p3);
$p3->refresh();
$p3Sales = SalesOrder::query()->with('lines')->findOrFail($p3->brewery_sales_order_id);
$p3Sales->lines()->firstOrFail()->update(['remaining_quantity' => '0.0000']);
$canceller->cancel($p3, 'P3 出荷指示済みなのでマイナス訂正');
$p3->refresh();
$p3Correction = SalesOrder::query()->find($p3->brewery_cancellation_sales_order_id);
$rows[] = ['pattern' => 'P3 出荷指示済訂正', 'retail_order' => $p3->purchase_order_no, 'retail_status' => $p3->status.' / '.$p3->brewery_cancel_status, 'brewery_status' => $p3Sales->order_number.' / '.$p3Sales->fresh()?->status, 'correction_order' => $p3Correction?->order_number];

$p4 = $makeDraftOrder($prefix.'-P4', 'P4 請求確定済手動');
$suggestions->sendToBrewery($p4);
$p4->refresh();
$p4Sales = SalesOrder::query()->with('lines.product', 'lines.unit')->findOrFail($p4->brewery_sales_order_id);
$p4Line = $p4Sales->lines->first();
$instruction = ShipmentInstruction::query()->create([
    'instruction_number' => 'SI-CODX-'.now()->format('His'),
    'status' => 'instructed',
    'customer_id' => $p4Sales->customer_id,
    'instruction_date' => now()->toDateString(),
    'scheduled_shipment_date' => now()->toDateString(),
    'note' => 'Codex P4 請求確定済み再現',
]);
$instructionLine = ShipmentInstructionLine::query()->create([
    'shipment_instruction_id' => $instruction->id,
    'line_no' => 1,
    'sales_order_id' => $p4Sales->id,
    'sales_order_line_id' => $p4Line->id,
    'product_id' => $p4Line->product_id,
    'quantity' => $p4Line->quantity,
    'picked_quantity' => $p4Line->quantity,
    'unit_id' => $p4Line->unit_id,
]);
$shipmentHeader = ShipmentHeader::query()->create([
    'document_number' => 'SH-CODX-'.now()->format('His'),
    'status' => 'confirmed',
    'customer_id' => $p4Sales->customer_id,
    'transaction_category_id' => $p4Sales->transaction_category_id,
    'settlement_receivable_category_id' => $p4Sales->settlement_receivable_category_id,
    'billing_cycle_id' => $p4Sales->billing_cycle_id,
    'document_date' => now()->toDateString(),
    'order_date' => $p4Sales->order_date,
    'scheduled_shipment_date' => now()->toDateString(),
    'actual_shipment_date' => now()->toDateString(),
    'sales_recorded_on' => now()->toDateString(),
    'billing_target_date' => now()->toDateString(),
    'source_shipment_instruction_id' => $instruction->id,
]);
$shipmentLine = ShipmentLine::query()->create([
    'shipment_header_id' => $shipmentHeader->id,
    'line_no' => 1,
    'product_id' => $p4Line->product_id,
    'quantity' => $p4Line->quantity,
    'unit_id' => $p4Line->unit_id,
    'shipment_instruction_line_id' => $instructionLine->id,
    'confirmed_quantity' => $p4Line->quantity,
    'confirmed_unit_price' => '1500.0000',
    'confirmed_at' => now(),
]);
$invoice = InvoiceHeader::query()->create([
    'invoice_number' => 'INV-CODX-'.now()->format('His'),
    'status' => 'confirmed',
    'document_type' => 'invoice',
    'customer_id' => $p4Sales->customer_id,
    'billing_cycle_id' => $p4Sales->billing_cycle_id,
    'invoice_date' => now()->toDateString(),
    'billing_period_start' => now()->startOfMonth()->toDateString(),
    'billing_period_end' => now()->endOfMonth()->toDateString(),
    'due_date' => now()->addMonth()->endOfMonth()->toDateString(),
    'subtotal_amount' => 18000,
    'tax_amount' => 1800,
    'total_amount' => 19800,
    'confirmed_at' => now(),
]);
InvoiceLine::query()->create([
    'invoice_header_id' => $invoice->id,
    'shipment_header_id' => $shipmentHeader->id,
    'shipment_line_id' => $shipmentLine->id,
    'line_no' => 1,
    'product_id' => $p4Line->product_id,
    'product_code' => $p4Line->product?->product_code ?? 'P4',
    'product_name' => $p4Line->product?->name ?? 'P4',
    'display_name' => $p4Line->product?->display_name ?? 'P4',
    'quantity' => $p4Line->quantity,
    'unit_code' => $p4Line->unit?->code ?? 'codex_bottle',
    'unit_name' => $p4Line->unit?->name ?? '本',
    'unit_price' => '1500.0000',
    'amount' => 18000,
    'tax_rate' => '0.1000',
    'tax_amount' => 1800,
    'total_amount' => 19800,
]);
$canceller->cancel($p4, 'P4 請求確定済みなので手動対応');
$p4->refresh();
$rows[] = ['pattern' => 'P4 請求確定済手動', 'retail_order' => $p4->purchase_order_no, 'retail_status' => $p4->status.' / '.$p4->brewery_cancel_status, 'brewery_status' => $p4Sales->order_number.' / '.$p4Sales->fresh()?->status, 'correction_order' => '-'];

echo json_encode([
    'login' => ['email' => 'codex-demo@example.com', 'password' => 'password'],
    'prefix' => $prefix,
    'retail_url' => 'http://localhost/retail/purchase-orders',
    'brewery_url' => 'http://localhost/sales-orders',
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
