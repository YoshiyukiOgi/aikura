<?php

namespace Database\Seeders;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\InvoiceHeader;
use App\Models\ProductionLot;
use App\Models\Product;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\ShipmentLine;
use App\Models\ShipmentLotAllocation;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Services\Tax\TaxRoundingService;
use Illuminate\Database\Seeder;

class DemoReturnScreenSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoFiftySalesOrderSeeder::class);

        if (InvoiceHeader::query()->where('invoice_number', 'like', 'DEMO-RET-INV-%')->exists()) {
            $this->ensureDemoSourceLots();
            return;
        }

        $customers = Customer::query()
            ->where('customer_code', 'like', 'DEMO-CUST-%')
            ->orderBy('customer_code')
            ->limit(10)
            ->get();
        $products = Product::query()
            ->where('product_code', 'like', 'DEMO-PROD-%')
            ->where('is_sales_available', true)
            ->orderBy('product_code')
            ->limit(12)
            ->get();
        $transactionCategory = TransactionCategory::query()->where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::query()->where('code', 'accounts_receivable_1')->firstOrFail();
        $billingCycle = BillingCycle::query()->where('code', 'monthly_end_next_month_end')->firstOrFail();
        $rounding = app(TaxRoundingService::class);

        foreach (range(1, 10) as $number) {
            $customer = $customers[($number - 1) % $customers->count()];
            $date = sprintf('2026-06-%02d', 10 + $number);

            $shipment = ShipmentHeader::create([
                'document_number' => sprintf('DEMO-RET-SHP-%03d', $number),
                'status' => 'billed',
                'customer_id' => $customer->id,
                'transaction_category_id' => $transactionCategory->id,
                'settlement_receivable_category_id' => $settlementCategory->id,
                'billing_cycle_id' => $billingCycle->id,
                'document_date' => $date,
                'actual_shipment_date' => $date,
                'sales_recorded_on' => $date,
                'billing_target_date' => $date,
                'liquor_tax_transfer_date' => $date,
                'note' => '返品画面確認用の確定済み出荷',
            ]);

            $invoice = InvoiceHeader::create([
                'invoice_number' => sprintf('DEMO-RET-INV-%03d', $number),
                'status' => 'confirmed',
                'document_type' => 'invoice',
                'customer_id' => $customer->id,
                'billing_cycle_id' => $customer->billing_cycle_id,
                'invoice_date' => $date,
                'billing_period_start' => '2026-06-01',
                'billing_period_end' => '2026-06-30',
                'due_date' => '2026-07-31',
                'previous_balance_amount' => '0.00',
                'period_payment_amount' => '0.00',
                'carried_forward_amount' => '0.00',
                'current_sales_amount' => '0.00',
                'current_tax_amount' => '0.00',
                'current_invoice_amount' => '0.00',
                'tax_calculation_unit' => 'line',
                'tax_rounding_method' => 'round',
                'amount_rounding_method' => 'round',
                'subtotal_amount' => '0.00',
                'tax_amount' => '0.00',
                'total_amount' => '0.00',
                'confirmed_at' => now(),
                'note' => '返品画面確認用の確定済み請求',
            ]);

            $subtotal = '0.00';
            $taxTotal = '0.00';
            $lineNo = 1;

            foreach ([$products[$number % $products->count()], $products[($number + 4) % $products->count()]] as $product) {
                $unit = Unit::query()->findOrFail($product->sales_unit_id);
                $quantity = number_format(($number % 4) + $lineNo, 4, '.', '');
                $unitPrice = (string) ($product->priceRules()->where('is_active', true)->orderByDesc('effective_from')->first()?->unit_price ?? '1500.0000');
                $amount = bcmul($quantity, $unitPrice, 2);
                $taxAmount = $rounding->round(bcmul($amount, '0.1000', 6), 'round');
                $total = bcadd($amount, $taxAmount, 2);

                $shipmentLine = $shipment->lines()->create([
                    'line_no' => $lineNo,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_id' => $unit->id,
                    'confirmed_product_code' => $product->product_code,
                    'confirmed_product_name' => $product->name,
                    'confirmed_display_name' => $product->display_name,
                    'confirmed_product_type' => $product->product_type,
                    'confirmed_unit_code' => $unit->code,
                    'confirmed_unit_name' => $unit->name,
                    'confirmed_quantity' => $quantity,
                    'confirmed_unit_price' => $unitPrice,
                    'confirmed_consumption_tax_category_code' => 'taxable_standard',
                    'confirmed_consumption_tax_category_name' => '課税 標準税率',
                    'confirmed_consumption_taxability' => 'taxable',
                    'confirmed_consumption_tax_rate' => '0.1000',
                    'confirmed_at' => now(),
                ]);

                $this->ensureShipmentLineLot($shipmentLine);

                $invoice->lines()->create([
                    'shipment_header_id' => $shipment->id,
                    'shipment_line_id' => $shipmentLine->id,
                    'line_no' => $lineNo++,
                    'product_id' => $product->id,
                    'product_code' => $product->product_code,
                    'product_name' => $product->name,
                    'display_name' => $product->display_name,
                    'quantity' => $quantity,
                    'unit_code' => $unit->code,
                    'unit_name' => $unit->name,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                    'consumption_tax_category_code' => 'taxable_standard',
                    'consumption_tax_category_name' => '課税 標準税率',
                    'consumption_taxability' => 'taxable',
                    'tax_rate' => '0.1000',
                    'tax_amount' => $taxAmount,
                    'total_amount' => $total,
                ]);

                $subtotal = bcadd($subtotal, $amount, 2);
                $taxTotal = bcadd($taxTotal, $taxAmount, 2);
            }

            $invoice->update([
                'current_sales_amount' => $subtotal,
                'current_tax_amount' => $taxTotal,
                'current_invoice_amount' => bcadd($subtotal, $taxTotal, 2),
                'subtotal_amount' => $subtotal,
                'tax_amount' => $taxTotal,
                'total_amount' => bcadd($subtotal, $taxTotal, 2),
            ]);
        }

        $this->ensureDemoSourceLots();
    }

    private function ensureDemoSourceLots(): void
    {
        ShipmentLine::query()
            ->whereHas('shipmentHeader', fn ($query) => $query->where('document_number', 'like', 'DEMO-RET-SHP-%'))
            ->orderBy('id')
            ->get()
            ->each(fn (ShipmentLine $line): ShipmentLine => $this->ensureShipmentLineLot($line));
    }

    private function ensureShipmentLineLot(ShipmentLine $line): ShipmentLine
    {
        if (ShipmentLotAllocation::query()->where('shipment_line_id', $line->id)->whereNull('cancelled_at')->exists()) {
            return $line;
        }

        $lot = ProductionLot::query()
            ->where('product_id', $line->product_id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->firstOrFail();

        $location = $lot->stockLocation
            ?? StockLocation::query()
                ->where('is_default_shipping_location', true)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->firstOrFail();

        ShipmentLotAllocation::create([
            'status' => 'confirmed',
            'shipment_header_id' => $line->shipment_header_id,
            'shipment_line_id' => $line->id,
            'product_id' => $line->product_id,
            'production_lot_id' => $lot->id,
            'stock_location_id' => $location->id,
            'unit_id' => $line->unit_id,
            'quantity' => $line->quantity,
            'allocated_at' => now(),
            'confirmed_at' => now(),
            'reason' => '返品画面デモ用の元出荷ロット',
        ]);

        StockMovement::updateOrCreate(
            [
                'source_type' => 'demo_return_screen_seeder',
                'source_document_number' => $line->shipmentHeader?->document_number,
                'source_line_no' => $line->line_no,
            ],
            [
                'status' => 'confirmed',
                'movement_type' => 'shipment',
                'movement_date' => $line->shipmentHeader?->actual_shipment_date?->toDateString()
                    ?? $line->shipmentHeader?->document_date?->toDateString()
                    ?? '2026-06-15',
                'product_id' => $line->product_id,
                'stock_location_id' => $location->id,
                'unit_id' => $line->unit_id,
                'quantity' => bcmul((string) $line->quantity, '-1', 4),
                'source_shipment_header_id' => $line->shipment_header_id,
                'source_shipment_line_id' => $line->id,
                'production_lot_id' => $lot->id,
                'lot_code' => $lot->lot_code,
                'confirmed_at' => now(),
                'cancelled_at' => null,
                'cancelled_reason' => null,
                'reason' => '返品画面デモ用の元出荷在庫移動',
            ],
        );

        return $line;
    }
}
