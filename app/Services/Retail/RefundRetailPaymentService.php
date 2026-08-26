<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundRetailPaymentService
{
    public function __construct(private readonly RetailDocumentNumber $numbers) {}

    public function refund(RetailPayment $payment, array $data): RetailPayment
    {
        return DB::connection(config('retail.database.connection', 'retail'))->transaction(function () use ($payment, $data): RetailPayment {
            $payment = RetailPayment::query()->with('adjustmentPayments')->lockForUpdate()->findOrFail($payment->id);

            if ($payment->adjustment_type === 'refund') {
                throw ValidationException::withMessages(['payment' => '返金伝票から返金は作成できません。']);
            }

            $amount = (float) $data['amount'];
            $refunded = (float) $payment->adjustmentPayments()
                ->where('adjustment_type', 'refund')
                ->sum('amount');
            $refundable = (float) $payment->amount + $refunded;

            if ($amount <= 0 || $amount > $refundable) {
                throw ValidationException::withMessages(['amount' => '返金額が返金可能額を超えています。']);
            }

            $refund = RetailPayment::query()->create([
                'payment_no' => $this->numbers->paymentNo(),
                'retail_customer_id' => $payment->retail_customer_id,
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'status' => 'posted',
                'original_retail_payment_id' => $payment->id,
                'adjustment_type' => 'refund',
                'amount' => -1 * $amount,
                'unapplied_amount' => 0,
                'note' => '返金: '.$payment->payment_no,
                'adjustment_reason' => $data['reason'],
            ]);

            if ($amount >= $refundable) {
                $payment->forceFill(['status' => 'refunded'])->save();
            } else {
                $payment->forceFill(['status' => 'partial_refund'])->save();
            }

            return $refund->load(['customer', 'originalPayment']);
        });
    }
}
