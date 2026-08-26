<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailDelivery;
use App\Models\Retail\RetailDocumentSequence;
use App\Models\Retail\RetailInvoice;
use App\Models\Retail\RetailPayment;
use App\Models\Retail\RetailPurchaseOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RetailDocumentNumber
{
    public function deliveryNo(): string
    {
        return $this->next('RD', RetailDelivery::class, 'delivery_no');
    }

    public function invoiceNo(): string
    {
        return $this->next('RI', RetailInvoice::class, 'invoice_no');
    }

    public function paymentNo(): string
    {
        return $this->next('RP', RetailPayment::class, 'payment_no');
    }

    public function purchaseOrderNo(): string
    {
        return $this->next('RPO', RetailPurchaseOrder::class, 'purchase_order_no');
    }

    /**
     * @param  class-string  $model
     */
    private function next(string $prefix, string $model, string $column): string
    {
        $date = Carbon::today()->format('Ymd');
        $base = "{$prefix}-{$date}-";

        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($prefix, $model, $column, $date, $base): string {
            $sequence = RetailDocumentSequence::query()
                ->where('code', $prefix)
                ->whereDate('document_date', Carbon::today()->toDateString())
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = RetailDocumentSequence::query()->create([
                    'code' => $prefix,
                    'document_date' => Carbon::today()->toDateString(),
                    'current_number' => $this->maxExistingNumber($model, $column, $base),
                ]);
            }

            $sequence->increment('current_number');

            return $base.str_pad((string) $sequence->refresh()->current_number, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * @param  class-string  $model
     */
    private function maxExistingNumber(string $model, string $column, string $base): int
    {
        $latest = $model::query()
            ->where($column, 'like', $base.'%')
            ->orderByDesc($column)
            ->value($column);

        if (! is_string($latest) || ! str_starts_with($latest, $base)) {
            return 0;
        }

        return max(0, (int) substr($latest, strlen($base)));
    }
}
