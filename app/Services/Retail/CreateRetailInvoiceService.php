<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailCustomer;
use App\Models\Retail\RetailInvoice;
use App\Models\Retail\RetailSale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRetailInvoiceService
{
    public function __construct(private readonly RetailDocumentNumber $numbers) {}

    public function createForCustomer(RetailCustomer $customer, array $data): RetailInvoice
    {
        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($customer, $data): RetailInvoice {
            $billingMethod = $data['billing_method'];
            if ($billingMethod === 'none' || ! $customer->invoice_required) {
                throw ValidationException::withMessages(['customer' => 'この顧客は請求書発行対象ではありません。']);
            }

            $sales = RetailSale::query()
                ->with('items')
                ->where('retail_customer_id', $customer->id)
                ->where('sale_type', 'credit')
                ->where('status', '<>', 'cancelled')
                ->whereDoesntHave('items', fn (Builder $query) => $query->whereHas('invoiceLine.invoice', fn (Builder $query) => $query->where('status', '<>', 'cancelled')))
                ->when($billingMethod === 'monthly', fn (Builder $query) => $query->whereDate('sale_date', '<=', $data['closing_date']))
                ->when($billingMethod === 'per_sale', fn (Builder $query) => $query->whereKey($data['retail_sale_id']))
                ->orderBy('sale_date')
                ->lockForUpdate()
                ->get();

            if ($sales->isEmpty()) {
                throw ValidationException::withMessages(['customer' => '請求対象の掛売がありません。']);
            }

            $subtotal = (float) $sales->sum('subtotal_amount');
            $tax = (float) $sales->sum('tax_amount');
            $total = $subtotal + $tax;

            $invoice = RetailInvoice::query()->create([
                'invoice_no' => $this->numbers->invoiceNo(),
                'retail_customer_id' => $customer->id,
                'invoice_date' => $data['invoice_date'],
                'closing_date' => $data['closing_date'],
                'due_date' => $data['due_date'] ?? null,
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => 0,
                'balance_amount' => $total,
                'status' => 'open',
                'note' => $data['note'] ?? null,
            ]);

            foreach ($sales as $sale) {
                foreach ($sale->items as $item) {
                    $invoice->lines()->create([
                        'retail_sale_id' => $sale->id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'tax_amount' => $item->tax_amount,
                        'line_amount' => $item->line_amount,
                    ]);
                }

                $sale->forceFill(['closed_at' => now()])->save();
            }

            return $invoice->load(['customer', 'lines.sale']);
        });
    }
}
