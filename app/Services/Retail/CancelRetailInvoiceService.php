<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailInvoice;
use App\Models\Retail\RetailSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelRetailInvoiceService
{
    public function cancel(RetailInvoice $invoice, string $reason): RetailInvoice
    {
        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($invoice, $reason): RetailInvoice {
            $invoice = RetailInvoice::query()->with('lines')->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === 'cancelled') {
                throw ValidationException::withMessages(['invoice' => 'この請求書は既に取消済みです。']);
            }

            if ((float) $invoice->paid_amount > 0) {
                throw ValidationException::withMessages(['invoice' => '入金済みの請求書は取消できません。赤伝で相殺、または入金返金で処理してください。']);
            }

            $saleIds = $invoice->lines->pluck('retail_sale_id')->filter()->unique()->all();
            $invoice->forceFill([
                'status' => 'cancelled',
                'balance_amount' => 0,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            foreach ($saleIds as $saleId) {
                $hasActiveInvoice = RetailInvoice::query()
                    ->where('id', '<>', $invoice->id)
                    ->where('status', '<>', 'cancelled')
                    ->whereHas('lines', fn ($query) => $query->where('retail_sale_id', $saleId))
                    ->exists();

                if (! $hasActiveInvoice) {
                    RetailSale::query()->whereKey($saleId)->update(['closed_at' => null]);
                }
            }

            return $invoice->refresh()->load(['customer', 'lines.sale']);
        });
    }
}
