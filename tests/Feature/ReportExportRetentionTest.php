<?php

namespace Tests\Feature;

use App\Exceptions\Reports\ReportExportRetentionException;
use App\Models\Product;
use App\Models\ReportExport;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Reports\ReportExportRetentionService;
use Database\Seeders\ProductUnitMasterSeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(Filesystem::class)->deleteDirectory(storage_path('app/reports/retention-test'));

        parent::tearDown();
    }

    public function test_it_verifies_report_export_file_size_and_checksum(): void
    {
        $export = $this->createReportExport('retention report content');

        $verification = app(ReportExportRetentionService::class)->verifyFile($export);

        $this->assertTrue($verification->verified);
        $this->assertSame($export->file_size, $verification->fileSize);
        $this->assertSame($export->checksum_sha256, $verification->checksumSha256);
    }

    public function test_it_rejects_missing_report_export_file(): void
    {
        $export = $this->createReportExport('retention report content');
        app(Filesystem::class)->delete(storage_path('app/'.$export->file_path));

        $this->expectException(ReportExportRetentionException::class);

        app(ReportExportRetentionService::class)->verifyFile($export);
    }

    public function test_it_rejects_tampered_report_export_file(): void
    {
        $export = $this->createReportExport('retention report content');
        app(Filesystem::class)->put(storage_path('app/'.$export->file_path), 'tampered report content');

        $this->expectException(ReportExportRetentionException::class);

        app(ReportExportRetentionService::class)->verifyFile($export);
    }

    public function test_it_lists_reissue_history_with_output_reasons(): void
    {
        $source = $this->createSourceMovement();
        $first = $this->createReportExport('first report content', $source, 'first issue', now()->subMinute());
        $second = $this->createReportExport('second report content', $source, 'second issue', now());

        $history = app(ReportExportRetentionService::class)
            ->history('stock_balance', StockMovement::class, $source->id);

        $this->assertCount(2, $history);
        $this->assertSame($second->id, $history[0]->id);
        $this->assertSame('second issue', $history[0]->reason);
        $this->assertSame($first->id, $history[1]->id);
        $this->assertSame('first issue', $history[1]->reason);
    }

    private function createReportExport(
        string $content,
        ?StockMovement $source = null,
        string $reason = 'retention test',
        mixed $generatedAt = null,
    ): ReportExport {
        $source ??= $this->createSourceMovement();
        $path = 'reports/retention-test/'.uniqid('report-', true).'.txt';
        $absolutePath = storage_path('app/'.$path);

        app(Filesystem::class)->ensureDirectoryExists(dirname($absolutePath));
        app(Filesystem::class)->put($absolutePath, $content);

        return ReportExport::create([
            'report_type' => 'stock_balance',
            'format' => 'txt',
            'status' => 'generated',
            'exportable_type' => $source::class,
            'exportable_id' => $source->id,
            'disk' => 'local',
            'file_path' => $path,
            'file_name' => basename($path),
            'mime_type' => 'text/plain',
            'file_size' => strlen($content),
            'checksum_sha256' => hash('sha256', $content),
            'generated_at' => $generatedAt ?? now(),
            'reason' => $reason,
        ]);
    }

    private function createSourceMovement(): StockMovement
    {
        $this->seed([
            ProductUnitMasterSeeder::class,
            StockLocationSeeder::class,
        ]);

        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();
        $product = Product::create([
            'product_code' => 'RETENTION-REPORT-SAKE-001',
            'product_type' => 'sake',
            'name' => 'Retention Report Sake',
            'display_name' => 'Retention Report Sake 720ml',
            'base_unit_id' => $unit->id,
            'sales_unit_id' => $unit->id,
            'inventory_unit_id' => $unit->id,
            'is_alcohol' => true,
            'is_inventory_managed' => true,
        ]);

        return StockMovement::create([
            'status' => 'confirmed',
            'movement_type' => 'inventory_adjustment',
            'movement_date' => '2026-06-15',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
            'quantity' => '1.0000',
            'confirmed_at' => now(),
        ]);
    }
}
