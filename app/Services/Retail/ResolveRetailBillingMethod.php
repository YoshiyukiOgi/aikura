<?php

namespace App\Services\Retail;

use App\Models\Retail\RetailCompanySetting;
use App\Models\Retail\RetailCustomer;

class ResolveRetailBillingMethod
{
    public function resolve(RetailCustomer $customer, RetailCompanySetting $companySetting): string
    {
        if (in_array($customer->billing_method, ['monthly', 'per_sale', 'none'], true)) {
            return $customer->billing_method;
        }

        return $companySetting->invoice_policy === 'per_invoice' ? 'per_sale' : 'monthly';
    }

    public function label(string $method): string
    {
        return match ($method) {
            'monthly' => '締め請求',
            'per_sale' => '都度請求',
            'none' => '請求なし',
            default => '会社設定に従う',
        };
    }
}
