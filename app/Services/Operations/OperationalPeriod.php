<?php

namespace App\Services\Operations;

use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class OperationalPeriod
{
    public const DEFAULT_START_DATE = '2026-07-01';

    private ?string $startDate = null;

    public function startDate(): string
    {
        if ($this->startDate !== null) {
            return $this->startDate;
        }

        if (! Schema::hasTable('app_settings')) {
            return $this->startDate = self::DEFAULT_START_DATE;
        }

        return $this->startDate = AppSetting::values(['operational_start_date' => self::DEFAULT_START_DATE])['operational_start_date']
            ?: self::DEFAULT_START_DATE;
    }

    public function lockEndDate(): string
    {
        return CarbonImmutable::parse($this->startDate())->subDay()->toDateString();
    }

    public function isLocked(?string $date): bool
    {
        return $date !== null && CarbonImmutable::parse($date)->lt(CarbonImmutable::parse($this->startDate()));
    }

    public function ensureOpen(?string $date, string $label = '対象日'): void
    {
        abort_if(
            $this->isLocked($date),
            422,
            "{$label}が{$this->lockEndDate()}以前のデータはAccess移行済み期間のため操作できません。"
        );
    }

    public function applyVisiblePeriod(Builder $query, string $column): Builder
    {
        return $query->whereDate($column, '>=', $this->startDate());
    }

    public function applyVisiblePeriodOrImportedHistory(
        Builder $query,
        string $column,
        Closure $importedHistory,
    ): Builder {
        return $query->where(function (Builder $visible) use ($column, $importedHistory): void {
            $visible
                ->whereDate($column, '>=', $this->startDate())
                ->orWhere($importedHistory);
        });
    }
}
