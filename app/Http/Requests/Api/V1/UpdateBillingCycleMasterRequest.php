<?php

namespace App\Http\Requests\Api\V1;

class UpdateBillingCycleMasterRequest extends StoreBillingCycleMasterRequest
{
    public function rules(): array
    {
        return [
            ...self::masterRules(),
            'change_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
