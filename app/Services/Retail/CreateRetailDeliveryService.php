<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailDelivery;
use App\Models\Retail\RetailSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRetailDeliveryService
{
    public function __construct(private readonly RetailDocumentNumber $numbers) {}

    public function createFromSale(RetailSale $sale, array $data): RetailDelivery
    {
        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($sale, $data): RetailDelivery {
            $sale = RetailSale::query()->lockForUpdate()->findOrFail($sale->id);
            $sale->load(['customer', 'items']);
            if ($sale->status === 'cancelled' || $sale->correction_type === 'credit_note') {
                throw ValidationException::withMessages(['sale' => '取消済みまたは赤伝の販売から納品書は作成できません。']);
            }

            $existingDelivery = RetailDelivery::query()
                ->where('retail_sale_id', $sale->id)
                ->where('status', 'issued')
                ->oldest('id')
                ->first();
            if ($existingDelivery) {
                return $existingDelivery->load(['customer', 'sale', 'lines']);
            }

            $delivery = RetailDelivery::query()->create([
                'delivery_no' => $this->numbers->deliveryNo(),
                'retail_customer_id' => $sale->retail_customer_id,
                'retail_sale_id' => $sale->id,
                'delivery_date' => $data['delivery_date'],
                'delivery_name' => $data['delivery_name'] ?? $sale->customer?->name ?? 'お客様各位',
                'delivery_postal_code' => $data['delivery_postal_code'] ?? $sale->customer?->postal_code,
                'delivery_address1' => $data['delivery_address1'] ?? $sale->customer?->address1,
                'delivery_address2' => $data['delivery_address2'] ?? $sale->customer?->address2,
                'billing_method_snapshot' => $data['billing_method'],
                'status' => 'issued',
                'issued_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

            foreach ($sale->items as $item) {
                $delivery->lines()->create([
                    'retail_sale_item_id' => $item->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->tax_amount,
                    'line_amount' => $item->line_amount,
                ]);
            }

            return $delivery->load(['customer', 'sale', 'lines']);
        });
    }
}
