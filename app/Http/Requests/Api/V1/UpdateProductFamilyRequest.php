<?php

namespace App\Http\Requests\Api\V1;

class UpdateProductFamilyRequest extends StoreProductFamilyRequest
{
    public function rules(): array
    {
        return [
            ...self::familyRules(),
            'change_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [];
    }
}
