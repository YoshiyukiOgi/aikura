<?php

namespace App\Services;

use App\Models\AccessMigrationBatch;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RepairAccessProductNames
{
    public function __construct(private readonly AccessProductNameFormatter $formatter) {}

    public function repair(AccessMigrationBatch $batch): array
    {
        return DB::transaction(function () use ($batch): array {
            $mainNames = $this->sourceRows($batch, '主商品')
                ->mapWithKeys(fn ($row) => [(string) $row->source_key => $this->payload($row)]);
            $summary = [
                'products_examined' => 0,
                'products_changed' => 0,
                'shipment_lines_updated' => 0,
                'production_lots_updated' => 0,
                'repaired_at' => now()->toIso8601String(),
            ];

            foreach ($this->sourceRows($batch, '商品マスター') as $row) {
                $source = $this->payload($row);
                $sourceKey = (string) $row->source_key;
                $main = $mainNames->get((string) ($source['主商品ID'] ?? ''));
                $names = $this->formatter->format($source, $main);
                $product = Product::query()
                    ->where('product_code', 'ITARO-P-'.str_pad($sourceKey, 5, '0', STR_PAD_LEFT))
                    ->first();
                if ($product === null) {
                    throw new RuntimeException("移行済み商品が見つかりません: {$sourceKey}");
                }

                $summary['products_examined']++;
                if ($product->name !== $names['name'] || $product->display_name !== $names['display_name'] || $product->search_key !== $names['search_key']) {
                    $product->update($names);
                    $summary['products_changed']++;
                }

                $summary['shipment_lines_updated'] += DB::table('shipment_lines')
                    ->where('product_id', $product->id)
                    ->whereNotNull('legacy_access_line_id')
                    ->update([
                        'confirmed_product_name' => $names['name'],
                        'confirmed_display_name' => $names['display_name'],
                        'updated_at' => now(),
                    ]);

                $lots = DB::table('production_lots')
                    ->where('note', 'like', "%batch={$batch->id};%商品ID={$sourceKey};%")
                    ->get(['id', 'lot_code', 'legacy_lot_text']);
                foreach ($lots as $lot) {
                    $legacyLotText = trim((string) ($lot->legacy_lot_text ?? ''));
                    DB::table('production_lots')->where('id', $lot->id)->update([
                        'display_name' => mb_substr($names['display_name'].($legacyLotText !== '' ? " / {$legacyLotText}" : ''), 0, 160),
                        'search_key' => trim("{$lot->lot_code} {$product->product_code} {$names['display_name']} {$legacyLotText}"),
                        'updated_at' => now(),
                    ]);
                    $summary['production_lots_updated']++;
                }
            }

            $validationSummary = $batch->validation_summary ?? [];
            $validationSummary['repairs']['product_names'] = $summary;
            $batch->update(['validation_summary' => $validationSummary]);

            return $summary;
        });
    }

    private function sourceRows(AccessMigrationBatch $batch, string $table)
    {
        return DB::table('access_migration_staging_rows')
            ->where('batch_id', $batch->id)
            ->where('source_table', $table)
            ->orderBy('source_row_number')
            ->get();
    }

    private function payload(object $row): array
    {
        return is_array($row->payload) ? $row->payload : json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
    }
}
