<?php

namespace App\Services\Inventory;

use App\Models\AppSetting;
use App\Models\ProductionLot;
use DomainException;

class EvaluateRepackagingAlcoholWarningService
{
    /**
     * @param array<int, array{production_lot_id:int, quantity:string|int|float}> $lines
     * @return array{has_warning:bool,source_average:?string,allowed_min:?string,allowed_max:?string,warnings:array<int, array{code:string,message:string}>}
     */
    public function evaluate(array $lines): array
    {
        $settings = AppSetting::values([
            'alcohol_tolerance_lower' => '0.90',
            'alcohol_tolerance_upper' => '0.90',
        ]);
        $lots = ProductionLot::query()
            ->whereIn('id', array_column($lines, 'production_lot_id'))
            ->get()
            ->keyBy('id');
        $sources = array_values(array_filter($lines, fn (array $line): bool => (float) $line['quantity'] < 0));
        $destinations = array_values(array_filter($lines, fn (array $line): bool => (float) $line['quantity'] > 0));
        $warnings = [];

        if ($sources === []) {
            $warnings[] = ['code' => 'source_missing', 'message' => '出庫側ロット（負数）が指定されていません。'];
        }
        if ($destinations === []) {
            $warnings[] = ['code' => 'destination_missing', 'message' => '入庫側ロット（正数）が指定されていません。'];
        }

        foreach ($lines as $line) {
            $lot = $lots->get((int) $line['production_lot_id']);
            if (! $lot instanceof ProductionLot) {
                continue;
            }
            if ($lot->analysis_status !== 'confirmed' || $lot->alcohol_percentage === null) {
                $warnings[] = [
                    'code' => 'analysis_required',
                    'message' => "{$lot->display_name}（{$lot->lot_code}）はアルコール度数が確定していません。",
                ];
            }
        }

        $weightedAlcohol = 0.0;
        $totalWeight = 0.0;
        foreach ($sources as $line) {
            $lot = $lots->get((int) $line['production_lot_id']);
            if (! $lot instanceof ProductionLot || $lot->analysis_status !== 'confirmed' || $lot->alcohol_percentage === null) {
                continue;
            }
            $weight = abs((float) $line['quantity']);
            $weightedAlcohol += (float) $lot->alcohol_percentage * $weight;
            $totalWeight += $weight;
        }

        $sourceAverage = $totalWeight > 0 ? $weightedAlcohol / $totalWeight : null;
        $allowedMin = $sourceAverage === null ? null : $sourceAverage - (float) $settings['alcohol_tolerance_lower'];
        $allowedMax = $sourceAverage === null ? null : $sourceAverage + (float) $settings['alcohol_tolerance_upper'];

        if ($sourceAverage !== null) {
            foreach ($destinations as $line) {
                $lot = $lots->get((int) $line['production_lot_id']);
                if (! $lot instanceof ProductionLot || $lot->analysis_status !== 'confirmed' || $lot->alcohol_percentage === null) {
                    continue;
                }
                $actual = (float) $lot->alcohol_percentage;
                if ($actual < $allowedMin || $actual > $allowedMax) {
                    $warnings[] = [
                        'code' => 'out_of_range',
                        'message' => sprintf(
                            '%s（%s）の度数 %.1f%% は許容範囲 %.1f～%.1f%% 外です。',
                            $lot->display_name,
                            $lot->lot_code,
                            $actual,
                            $allowedMin,
                            $allowedMax,
                        ),
                    ];
                }
            }
        }

        return [
            'has_warning' => $warnings !== [],
            'source_average' => $sourceAverage === null ? null : number_format($sourceAverage, 2, '.', ''),
            'allowed_min' => $allowedMin === null ? null : number_format($allowedMin, 2, '.', ''),
            'allowed_max' => $allowedMax === null ? null : number_format($allowedMax, 2, '.', ''),
            'warnings' => $warnings,
        ];
    }

    /** @param array<int, CreateNonSalesStockOperationLineData> $lines */
    public function ensureAcknowledged(array $lines, bool $acknowledged): void
    {
        $result = $this->evaluate(array_map(
            fn (CreateNonSalesStockOperationLineData $line): array => [
                'production_lot_id' => $line->productionLotId,
                'quantity' => $line->quantity,
            ],
            $lines,
        ));

        if ($result['has_warning'] && ! $acknowledged) {
            throw new DomainException('詰替ロットのアルコール度数に警告があります。内容を確認してから登録してください。');
        }
    }
}
