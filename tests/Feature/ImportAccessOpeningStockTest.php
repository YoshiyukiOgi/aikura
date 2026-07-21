<?php

namespace Tests\Feature;

use App\Models\AccessMigrationBatch;
use App\Models\Product;
use App\Models\ProductionLot;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\ExportAccessDetailStockWorkbook;
use App\Services\ImportAccessOpeningStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportAccessOpeningStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_link_opening_stock_by_legacy_detail_id_only(): void
    {
        $bottle = Unit::query()->create([
            'code' => 'bottle',
            'name' => '本',
            'symbol' => '本',
            'unit_type' => 'count',
        ]);
        $piece = Unit::query()->create([
            'code' => 'piece',
            'name' => '件',
            'symbol' => '件',
            'unit_type' => 'count',
        ]);
        $milliliter = Unit::query()->create([
            'code' => 'milliliter',
            'name' => 'ml',
            'symbol' => 'ml',
            'unit_type' => 'volume',
        ]);
        $location = StockLocation::query()->create([
            'code' => 'main_brewery',
            'name' => '本社蔵',
            'location_type' => 'warehouse',
            'is_inventory_managed' => true,
            'is_active' => true,
        ]);
        $alcoholProduct = Product::query()->create([
            'product_code' => 'ITARO-P-05403',
            'product_type' => 'sake',
            'name' => '伊太郎吟醸 あらばしり 720ml',
            'display_name' => '伊太郎吟醸 あらばしり 720ml',
            'base_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.00',
            'is_alcohol' => true,
            'legacy_code' => '5403',
        ]);
        $feeProduct = Product::query()->create([
            'product_code' => 'ITARO-P-08210',
            'product_type' => 'goods',
            'name' => '送料 高知県内 1件',
            'display_name' => '送料 高知県内 1件',
            'base_unit_id' => $piece->id,
            'inventory_unit_id' => $piece->id,
            'capacity_value' => '1.0000',
            'is_alcohol' => false,
            'legacy_code' => '8210',
        ]);
        $legacyAlcoholLot = ProductionLot::query()->create([
            'lot_code' => 'ITARO-LOT-000415',
            'display_name' => $alcoholProduct->display_name.' / 初期詳細名',
            'status' => 'active',
            'stock_location_id' => $location->id,
            'unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.00',
            'analysis_status' => 'confirmed',
            'external_system_code' => 'ITARO-DETAIL-415',
            'legacy_lot_text' => '初期詳細名',
            'is_active' => true,
        ]);
        $batch = AccessMigrationBatch::query()->create([
            'status' => 'completed',
            'source_file_name' => 'Itaro-xp.accdb',
            'source_file_path' => 'Itaro-xp.accdb',
            'source_sha256' => str_repeat('a', 64),
            'source_size' => 1,
            'extractor_version' => 'test',
            'package_version' => 1,
            'manifest' => [],
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $calculator = new class($feeProduct) extends ExportAccessDetailStockWorkbook {
            public function __construct(private Product $feeProduct) {}

            public function calculateRows(AccessMigrationBatch $batch, string $asOfDate): array
            {
                return [
                    (object) [
                        'product_id' => $this->feeProduct->id,
                        'access_product_id' => 8210,
                        'access_detail_id' => 415,
                        'detail_name' => '初期詳細名',
                        'calculated_stock' => -32,
                    ],
                ];
            }
        };

        $summary = (new ImportAccessOpeningStock($calculator))->import($batch, '2026-06-30');

        $canonicalLot = ProductionLot::query()
            ->where('external_system_code', 'ITARO-PRODUCT-DETAIL-8210-415')
            ->firstOrFail();
        $movement = StockMovement::query()->where('source_type', 'access_opening_stock')->sole();

        $this->assertSame(1, $summary['lots_created']);
        $this->assertSame($canonicalLot->id, $movement->production_lot_id);
        $this->assertNotSame($legacyAlcoholLot->id, $movement->production_lot_id);
        $this->assertSame('1.0000', $canonicalLot->capacity_value);
        $this->assertNull($canonicalLot->alcohol_percentage);
    }
}
