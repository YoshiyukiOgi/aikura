<?php

namespace App\Services\Masters;

use App\Models\BillingCycle;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveBillingCycleMasterService
{
    private const CALCULATION_FIELDS = [
        'billing_method',
        'closing_day',
        'payment_month_offset',
        'payment_day',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function create(array $values): BillingCycle
    {
        return DB::transaction(function () use ($values): BillingCycle {
            $attributes = $this->normalize($values);
            $this->ensureNoDuplicate($attributes);
            $cycle = BillingCycle::query()->create([
                'code' => 'custom_'.Str::lower((string) Str::ulid()),
                ...$attributes,
            ]);
            $this->auditLogService->recordModelChange(
                event: 'billing_cycle_master.created',
                model: $cycle,
                afterValues: $cycle->getAttributes(),
                reason: $values['change_reason'] ?? '締日条件マスター新規登録',
            );

            return $cycle->refresh();
        });
    }

    public function update(BillingCycle $cycle, array $values): BillingCycle
    {
        return DB::transaction(function () use ($cycle, $values): BillingCycle {
            $attributes = $this->normalize($values);
            $usageCount = $this->usageCount($cycle);
            $calculationChanged = collect(self::CALCULATION_FIELDS)
                ->contains(fn (string $field): bool => $cycle->{$field} !== $attributes[$field]);
            if ($usageCount > 0 && $calculationChanged) {
                throw ValidationException::withMessages([
                    'billing_method' => '使用中の条件は計算内容を変更できません。「複製して新規」から新しい条件を登録してください。',
                ]);
            }
            $this->ensureNoDuplicate($attributes, $cycle);
            $before = $cycle->getAttributes();
            $cycle->fill($attributes)->save();
            $this->auditLogService->recordModelChange(
                event: 'billing_cycle_master.updated',
                model: $cycle,
                beforeValues: $before,
                afterValues: $cycle->getAttributes(),
                reason: $values['change_reason'],
            );

            return $cycle->refresh();
        });
    }

    private function normalize(array $values): array
    {
        $attributes = Arr::only($values, [
            'name', 'billing_method', 'closing_day', 'payment_month_offset',
            'payment_day', 'description', 'is_active',
        ]);
        $attributes['description'] = filled($attributes['description'] ?? null)
            ? trim((string) $attributes['description'])
            : null;
        if ($attributes['billing_method'] !== 'monthly_closing') {
            $attributes['closing_day'] = null;
        }
        if ($attributes['billing_method'] === 'cash_immediate') {
            $attributes['payment_month_offset'] = 0;
            $attributes['payment_day'] = null;
        }

        return $attributes;
    }

    private function ensureNoDuplicate(array $attributes, ?BillingCycle $except = null): void
    {
        $duplicate = BillingCycle::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->where('billing_method', $attributes['billing_method'])
            ->where('closing_day', $attributes['closing_day'])
            ->where('payment_month_offset', $attributes['payment_month_offset'])
            ->where('payment_day', $attributes['payment_day'])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => '同じ締日・入金条件がすでに登録されています。',
            ]);
        }
    }

    private function usageCount(BillingCycle $cycle): int
    {
        return $cycle->customers()->count()
            + $cycle->salesOrders()->count()
            + $cycle->shipments()->count()
            + $cycle->invoices()->count();
    }
}
