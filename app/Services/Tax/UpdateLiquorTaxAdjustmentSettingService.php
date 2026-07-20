<?php

namespace App\Services\Tax;

use App\Models\LiquorTaxAdjustmentSetting;
use App\Models\User;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateLiquorTaxAdjustmentSettingService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function update(array $data, User $user): LiquorTaxAdjustmentSetting
    {
        return DB::transaction(function () use ($data, $user): LiquorTaxAdjustmentSetting {
            $setting = LiquorTaxAdjustmentSetting::query()->firstOrCreate(
                ['manufacturing_site_code' => $data['manufacturing_site_code'] ?? 'main'],
            );
            $before = $setting->toArray();
            $setting->update([
                'approval_amount_threshold' => $data['approval_amount_threshold'],
                'approval_quantity_threshold_kl' => $data['approval_quantity_threshold_kl'],
                'is_active' => $data['is_active'],
            ]);
            $this->auditLogService->record(new AuditLogData(
                event: 'liquor_tax_adjustment_setting.updated',
                auditable: $setting,
                beforeValues: $before,
                afterValues: $setting->fresh()->toArray(),
                reason: $data['reason'],
                user: $user,
            ));

            return $setting->fresh();
        });
    }
}
