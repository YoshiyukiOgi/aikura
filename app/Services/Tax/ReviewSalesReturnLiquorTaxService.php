<?php

namespace App\Services\Tax;

use App\Models\SalesReturnLine;
use App\Models\User;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReviewSalesReturnLiquorTaxService
{
    public function __construct(
        private readonly EnsureLiquorTaxFilingPeriodIsOpenService $ensurePeriodIsOpenService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function review(SalesReturnLine $line, User $user, string $treatment, string $reason): SalesReturnLine
    {
        $reason = trim($reason);
        if (! in_array($treatment, ['eligible', 'not_eligible', 'review'], true) || $reason === '') {
            throw new DomainException('戻入控除判定と理由を入力してください。');
        }

        return DB::transaction(function () use ($line, $user, $treatment, $reason): SalesReturnLine {
            $line = SalesReturnLine::query()
                ->with(['salesReturnHeader', 'sourceShipmentLine.shipmentHeader', 'stockMovement.stockLocation'])
                ->lockForUpdate()->findOrFail($line->id);
            if ($line->salesReturnHeader->cancelled_at !== null) {
                throw new DomainException('取消済み返品は変更できません。');
            }
            $this->ensurePeriodIsOpenService->ensureOpen($line->salesReturnHeader->return_date->toDateString());

            if ($treatment === 'eligible') {
                $location = $line->stockMovement?->stockLocation;
                if ($location?->location_type !== 'brewery') {
                    throw new DomainException('戻入控除対象は製造場へ現物を戻した返品だけ指定できます。');
                }
                if ($line->sourceShipmentLine?->shipmentHeader?->confirmed_liquor_tax_treatment !== 'taxable') {
                    throw new DomainException('課税移出された元出荷だけ戻入控除対象にできます。');
                }
            }

            $before = $line->only(['liquor_tax_return_treatment', 'liquor_tax_return_reason']);
            $line->update([
                'liquor_tax_return_treatment' => $treatment,
                'liquor_tax_return_reason' => $reason,
                'liquor_tax_reviewed_by' => $user->id,
                'liquor_tax_reviewed_at' => now(),
            ]);
            $this->auditLogService->record(new AuditLogData(
                event: 'sales_return.liquor_tax_reviewed',
                auditable: $line->refresh(),
                beforeValues: $before,
                afterValues: $line->only(['liquor_tax_return_treatment', 'liquor_tax_return_reason']),
                reason: $reason,
            ));

            return $line->refresh();
        });
    }
}
