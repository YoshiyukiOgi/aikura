<?php

namespace App\Services\Tax;

use App\Models\LiquorTaxMonthlyFiling;
use App\Models\LiquorTaxReliefSetting;
use App\Models\User;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class CreateLiquorTaxReliefSettingService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function create(array $data, User $user): LiquorTaxReliefSetting
    {
        return DB::transaction(function () use ($data, $user): LiquorTaxReliefSetting {
            $site = $data['manufacturing_site_code'] ?? 'main';
            $effectiveFrom = CarbonImmutable::parse($data['effective_from']);
            $latest = LiquorTaxReliefSetting::query()
                ->where('manufacturing_site_code', $site)
                ->lockForUpdate()
                ->orderByDesc('effective_from')
                ->first();

            if ($latest && $effectiveFrom->lessThanOrEqualTo(CarbonImmutable::parse($latest->effective_from))) {
                throw new DomainException('適用開始日は現在の設定より後の日付にしてください。');
            }

            $lastConfirmed = LiquorTaxMonthlyFiling::query()
                ->where('manufacturing_site_code', $site)
                ->where('status', 'confirmed')
                ->max('period_end');
            if ($lastConfirmed && $effectiveFrom->lessThanOrEqualTo(CarbonImmutable::parse($lastConfirmed))) {
                throw new DomainException('確定済み申告期間に遡って軽減計算方式を変更できません。');
            }

            if ($data['scheme'] === 'legacy_scheme' && LiquorTaxReliefSetting::query()
                ->where('manufacturing_site_code', $site)->where('scheme', 'new_scheme')->exists()) {
                throw new DomainException('新制度へ移行した後は旧制度へ戻せません。');
            }

            if ($data['scheme'] === 'new_scheme') {
                $this->validateNewScheme($data, $effectiveFrom);
            }

            if ($latest?->effective_to === null) {
                $latest->update(['effective_to' => $effectiveFrom->subDay()->toDateString()]);
            }

            $setting = LiquorTaxReliefSetting::query()->create([
                'manufacturing_site_code' => $site,
                'scheme' => $data['scheme'],
                'effective_from' => $effectiveFrom->toDateString(),
                'effective_to' => null,
                'legacy_reduction_rate' => $data['legacy_reduction_rate'] ?? '0.2000',
                'legacy_annual_quantity_limit_kl' => $data['legacy_annual_quantity_limit_kl'] ?? '200.000000',
                'opening_eligible_quantity_kl' => $data['opening_eligible_quantity_kl'] ?? '0.000000',
                'opening_gross_tax_amount' => $data['opening_gross_tax_amount'] ?? '0.00',
                'prior_year_total_taxable_quantity_kl' => $data['prior_year_total_taxable_quantity_kl'] ?? null,
                'prior_year_peak_taxable_quantity_kl' => $data['prior_year_peak_taxable_quantity_kl'] ?? null,
                'approval_date' => $data['approval_date'] ?? null,
                'approval_reference' => $data['approval_reference'] ?? null,
                'selection_notice_date' => $data['selection_notice_date'] ?? null,
                'discontinuance_notice_date' => $data['discontinuance_notice_date'] ?? null,
                'calculation_rule_version' => $data['scheme'] === 'new_scheme' ? 'new-2024-v1' : 'legacy-2024-v1',
                'note' => $data['note'] ?? null,
                'is_active' => true,
            ]);

            $this->auditLogService->record(new AuditLogData(
                event: 'liquor_tax_relief_setting.created',
                auditable: $setting,
                afterValues: $setting->toArray(),
                reason: $data['reason'],
                user: $user,
            ));

            return $setting;
        });
    }

    private function validateNewScheme(array $data, CarbonImmutable $effectiveFrom): void
    {
        if ($effectiveFrom->month !== 4 || $effectiveFrom->day !== 1) {
            throw new DomainException('新制度への切替日は事業年度開始日の4月1日にしてください。');
        }
        foreach (['prior_year_total_taxable_quantity_kl', 'prior_year_peak_taxable_quantity_kl', 'approval_date', 'approval_reference'] as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                throw new DomainException('新制度には前年数量、前年最大品目数量、承認日、承認番号が必要です。');
            }
        }
        if (bccomp((string) $data['prior_year_total_taxable_quantity_kl'], '3000', 6) === 1) {
            throw new DomainException('前年の課税移出数量合計が3,000klを超える場合は新制度を適用できません。');
        }
    }
}
