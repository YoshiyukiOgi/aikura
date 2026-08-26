<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Collection;

class RetailCompany extends RetailModel
{
    protected $fillable = [
        'company_key',
        'name',
        'description',
        'representative_name',
        'postal_code',
        'address1',
        'address2',
        'phone',
        'fax',
        'email',
        'invoice_registration_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function activeOptions(): array
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (self $company): array => [
                $company->company_key => [
                    'name' => $company->name,
                    'description' => $company->description,
                ],
            ])
            ->all();
    }

    public static function activeByKey(?string $key): ?self
    {
        if ($key === null || $key === '') {
            return null;
        }

        return self::query()
            ->where('company_key', $key)
            ->where('is_active', true)
            ->first();
    }

    public static function allForManagement(): Collection
    {
        return self::query()->orderBy('is_active', 'desc')->orderBy('id')->get();
    }
}
