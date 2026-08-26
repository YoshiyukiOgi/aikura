<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailCustomer;
use App\Models\Retail\RetailInvoice;
use App\Models\Retail\RetailPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterRetailPaymentService
{
    public function __construct(private readonly RetailDocumentNumber $numbers) {}

    public function register(RetailCustomer $customer, array $data): RetailPayment
    {
        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($customer, $data): RetailPayment {
            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => '入金額は1円以上で入力してください。']);
            }

            $payment = RetailPayment::query()->create([
                'payment_no' => $this->numbers->paymentNo(),
                'retail_customer_id' => $customer->id,
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'amount' => $amount,
                'unapplied_amount' => $amount,
                'note' => $data['note'] ?? null,
            ]);

            $remaining = $amount;
            $invoices = RetailInvoice::query()
                ->where('retail_customer_id', $customer->id)
                ->where('balance_amount', '>', 0)
                ->orderBy('due_date')
                ->orderBy('invoice_date')
                ->lockForUpdate()
                ->get();

            foreach ($invoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $balance = (float) $invoice->balance_amount;
                $allocated = min($remaining, $balance);
                $remaining -= $allocated;

                $payment->allocations()->create([
                    'retail_invoice_id' => $invoice->id,
                    'allocated_amount' => $allocated,
                ]);

                $paid = (float) $invoice->paid_amount + $allocated;
                $newBalance = $balance - $allocated;
                $invoice->update([
                    'paid_amount' => $paid,
                    'balance_amount' => $newBalance,
                    'status' => $newBalance <= 0 ? 'paid' : 'partial',
                ]);
            }

            $payment->update(['unapplied_amount' => $remaining]);

            return $payment->load(['customer', 'allocations.invoice']);
        });
    }
}
