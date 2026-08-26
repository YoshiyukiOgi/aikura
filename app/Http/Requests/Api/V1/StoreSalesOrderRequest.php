<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'order_date' => ['required', 'date'],
            'requested_shipment_date' => ['nullable', 'date'],
            'requested_delivery_date' => ['nullable', 'date'],
            'billing_target_date' => ['nullable', 'date'],
            'customer_order_number' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', 'max:255'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'work_note' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
            'auto_release_to_shipping' => ['nullable', 'boolean'],
            'awaiting_shipment_instruction' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'lines.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'lines.*.note' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sourceType = (string) $this->input('source_type', '');
                $allowsNegative = in_array($sourceType, [
                    'retail_sale_correction',
                    'retail_sale_credit_note',
                    'retail_purchase_order_cancellation',
                ], true);

                foreach ((array) $this->input('lines', []) as $index => $line) {
                    $quantity = (string) ($line['quantity'] ?? '0');
                    if ($quantity === '' || ! is_numeric($quantity)) {
                        continue;
                    }

                    if ($allowsNegative) {
                        if (bccomp($quantity, '0', 4) === 0) {
                            $validator->errors()->add("lines.{$index}.quantity", '数量は0以外で入力してください。');
                        }
                    } elseif (bccomp($quantity, '0', 4) <= 0) {
                        $validator->errors()->add("lines.{$index}.quantity", '通常受注の数量は1以上で入力してください。');
                    }
                }
            },
        ];
    }
}
