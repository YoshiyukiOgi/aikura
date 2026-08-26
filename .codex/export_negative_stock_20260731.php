<?php

use App\Models\AccessMigrationBatch;
use App\Services\ExportAccessDetailStockWorkbook;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$batch = AccessMigrationBatch::query()->findOrFail(5);
$rows = collect(app(ExportAccessDetailStockWorkbook::class)->calculateRows($batch, '2026-07-31'))
    ->filter(fn (object $row): bool => (float) $row->calculated_stock < 0)
    ->values()
    ->map(fn (object $row): array => (array) $row)
    ->all();

$path = storage_path('app/access-migrations/reports/negative-stock-batch-5-2026-07-31.json');
if (! is_dir(dirname($path))) {
    mkdir(dirname($path), 0775, true);
}
file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

echo json_encode([
    'path' => $path,
    'count' => count($rows),
    'total' => array_sum(array_column($rows, 'calculated_stock')),
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
