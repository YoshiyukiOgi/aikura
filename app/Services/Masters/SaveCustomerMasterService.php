<?php

namespace App\Services\Masters;

use App\Models\Customer;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SaveCustomerMasterService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function create(array $values): Customer
    {
        return DB::transaction(function () use ($values): Customer {
            $customer = Customer::query()->create($this->attributes($values, includeCode: true));
            $this->auditLogService->recordModelChange(
                event: 'customer_master.created',
                model: $customer,
                afterValues: $customer->getAttributes(),
                reason: $values['change_reason'] ?? '取引先マスター新規登録',
            );
            $this->clearCustomerCaches();

            return $customer->refresh();
        });
    }

    public function update(Customer $customer, array $values): Customer
    {
        return DB::transaction(function () use ($customer, $values): Customer {
            $before = $customer->getAttributes();
            $customer->fill($this->attributes($values));
            $customer->save();
            $this->auditLogService->recordModelChange(
                event: 'customer_master.updated',
                model: $customer,
                beforeValues: $before,
                afterValues: $customer->getAttributes(),
                reason: $values['change_reason'],
            );
            $this->clearCustomerCaches();

            return $customer->refresh();
        });
    }

    private function attributes(array $values, bool $includeCode = false): array
    {
        $fields = [
            'name', 'name_kana', 'short_name', 'billing_name', 'postal_code', 'address1', 'address2',
            'phone', 'fax', 'email', 'contact_name', 'transaction_category_id',
            'settlement_receivable_category_id', 'billing_cycle_id', 'tax_rounding_method',
            'tax_calculation_unit', 'amount_rounding_method', 'invoice_required', 'note', 'is_active',
        ];
        if ($includeCode) {
            array_unshift($fields, 'customer_code');
        }

        $attributes = Arr::only($values, $fields);
        foreach (['name_kana', 'short_name', 'billing_name', 'postal_code', 'address1', 'address2', 'phone', 'fax', 'email', 'contact_name', 'note'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = $this->blankToNull($attributes[$field]);
            }
        }
        $attributes['disabled_at'] = ($attributes['is_active'] ?? true) ? null : now();
        $attributes['search_key'] = implode(' ', array_filter([
            $attributes['customer_code'] ?? null,
            $attributes['name'] ?? null,
            $attributes['name_kana'] ?? null,
            $attributes['short_name'] ?? null,
            $attributes['billing_name'] ?? null,
            $attributes['phone'] ?? null,
            $attributes['address1'] ?? null,
        ]));

        return $attributes;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function clearCustomerCaches(): void
    {
        Cache::forget('sales_order_page.customers');
    }
}
