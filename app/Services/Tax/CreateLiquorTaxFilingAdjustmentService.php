<?php

namespace App\Services\Tax;

use App\Models\LiquorTaxAdjustmentSetting;
use App\Models\LiquorTaxMonthlyFiling;
use App\Models\LiquorTaxMonthlyFilingAdjustment;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use DomainException;
use Illuminate\Support\Facades\DB;

class CreateLiquorTaxFilingAdjustmentService
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly RecalculateLiquorTaxFilingAdjustmentsService $recalculateService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function create(LiquorTaxMonthlyFiling $filing, User $user, array $data): LiquorTaxMonthlyFilingAdjustment
    {
        return DB::transaction(function () use ($filing, $user, $data): LiquorTaxMonthlyFilingAdjustment {
            $filing = LiquorTaxMonthlyFiling::query()->lockForUpdate()->findOrFail($filing->id);
            if ($filing->status !== 'draft') {
                throw new DomainException('確定済み申告には調整明細を追加できません。');
            }
            if (bccomp((string) $data['taxable_kl_adjustment'], '0', 6) === 0 && bccomp((string) $data['tax_amount_adjustment'], '0', 2) === 0) {
                throw new DomainException('数量または税額の調整値を入力してください。');
            }

            $setting = LiquorTaxAdjustmentSetting::firstOrCreate(
                ['manufacturing_site_code' => $filing->manufacturing_site_code],
                ['approval_amount_threshold' => 0, 'approval_quantity_threshold_kl' => 0, 'is_active' => true],
            );
            $approvalRequired = $setting->is_active && (
                bccomp(ltrim((string) $data['tax_amount_adjustment'], '-'), (string) $setting->approval_amount_threshold, 2) === 1
                || bccomp(ltrim((string) $data['taxable_kl_adjustment'], '-'), (string) $setting->approval_quantity_threshold_kl, 6) === 1
            );
            $adjustment = $filing->adjustments()->create([
                ...$data, 'line_no' => ((int) $filing->adjustments()->max('line_no')) + 1,
                'status' => 'active', 'approval_required' => $approvalRequired, 'created_by_user_id' => $user->id,
            ]);

            if ($approvalRequired) {
                $approval = $this->approvalService->request(
                    $user, ApprovalService::ACTION_LIQUOR_TAX_ADJUSTMENT, 'liquor_tax_adjustment', (string) $adjustment->id,
                    (string) $adjustment->description,
                    ['filing_id' => $filing->id, 'taxable_kl_adjustment' => $adjustment->taxable_kl_adjustment, 'tax_amount_adjustment' => $adjustment->tax_amount_adjustment],
                );
                $adjustment->update(['approval_request_id' => $approval->id]);
            }

            $this->recalculateService->recalculate($filing);
            $this->auditLogService->record(new AuditLogData(event: 'liquor_tax_adjustment.created', auditable: $adjustment, afterValues: $adjustment->toArray(), reason: $adjustment->description, user: $user));

            return $adjustment->refresh()->load(['category', 'approvalRequest', 'creator']);
        });
    }

    public function void(LiquorTaxMonthlyFilingAdjustment $adjustment, User $user, string $reason): LiquorTaxMonthlyFilingAdjustment
    {
        return DB::transaction(function () use ($adjustment, $user, $reason): LiquorTaxMonthlyFilingAdjustment {
            $adjustment = LiquorTaxMonthlyFilingAdjustment::query()->with('filing')->lockForUpdate()->findOrFail($adjustment->id);
            if ($adjustment->filing->status !== 'draft' || $adjustment->status !== 'active') {
                throw new DomainException('この調整明細は取消できません。');
            }
            if (trim($reason) === '') {
                throw new DomainException('取消理由を入力してください。');
            }
            $adjustment->update(['status' => 'voided', 'voided_by_user_id' => $user->id, 'voided_at' => now(), 'void_reason' => $reason]);
            $this->recalculateService->recalculate($adjustment->filing);
            $this->auditLogService->record(new AuditLogData(event: 'liquor_tax_adjustment.voided', auditable: $adjustment, reason: $reason, user: $user));

            return $adjustment->refresh()->load(['category', 'approvalRequest', 'creator']);
        });
    }
}
