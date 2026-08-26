<?php

use App\Models\AccessMigrationBatch;
use App\Models\ReceivableMonthlyBalance;
use App\Services\Inventory\PrepareAccessStockMovementsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$statementPath = storage_path('app/access-migrations/statements/2026-07-paper-balances.csv');
$handle = fopen($statementPath, 'rb');
$header = fgetcsv($handle);
$header[0] = ltrim($header[0], "\xEF\xBB\xBF");
$indexes = array_flip($header);
$paper = [];
while (($values = fgetcsv($handle)) !== false) {
    $paper[$values[$indexes['customer_code']]] = number_format((float) $values[$indexes['billed_amount']], 2, '.', '');
}
fclose($handle);

$drafts = ReceivableMonthlyBalance::query()
    ->where('year', 2026)
    ->where('month', 7)
    ->get()
    ->keyBy('customer_code');
$paperDifferences = [];
foreach ($paper as $code => $amount) {
    $actual = (string) ($drafts->get($code)?->outstanding_amount ?? 'missing');
    if ($actual === 'missing' || bccomp($amount, $actual, 2) !== 0) {
        $paperDifferences[] = compact('code', 'amount', 'actual');
    }
}
$extraNonzero = $drafts
    ->reject(fn ($row) => isset($paper[$row->customer_code]))
    ->filter(fn ($row) => bccomp((string) $row->outstanding_amount, '0.00', 2) !== 0)
    ->map(fn ($row) => ['code' => $row->customer_code, 'amount' => (string) $row->outstanding_amount])
    ->values();

$stock = app(PrepareAccessStockMovementsService::class)->reconcile(
    AccessMigrationBatch::query()->findOrFail(6),
    '2026-07-01',
    '2026-07-31',
);
$inventoryCount = DB::table('inventory_count_headers')
    ->where('year', 2026)
    ->where('month', 7)
    ->first();

$result = [
    'paper_rows' => count($paper),
    'paper_total' => array_reduce($paper, fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), '0.00'),
    'receivable_draft_rows' => $drafts->count(),
    'receivable_draft_total' => $drafts->reduce(fn (string $sum, $row): string => bcadd($sum, (string) $row->outstanding_amount, 2), '0.00'),
    'paper_difference_count' => count($paperDifferences),
    'paper_differences' => array_slice($paperDifferences, 0, 20),
    'extra_nonzero_count' => $extraNonzero->count(),
    'extra_nonzero' => $extraNonzero->take(20)->all(),
    'stock' => $stock,
    'duplicate_shipment_movements' => DB::table('stock_movements')
        ->whereNotNull('source_shipment_line_id')
        ->whereBetween('movement_date', ['2026-07-01', '2026-07-31'])
        ->select('source_shipment_line_id')
        ->groupBy('source_shipment_line_id')
        ->havingRaw('COUNT(*) > 1')
        ->get()->count(),
    'unlinked_non_sales_lines' => DB::table('non_sales_stock_operation_lines as l')
        ->join('non_sales_stock_operation_headers as h', 'h.id', '=', 'l.non_sales_stock_operation_header_id')
        ->join('products as p', 'p.id', '=', 'l.product_id')
        ->whereBetween('h.operation_date', ['2026-07-01', '2026-07-31'])
        ->where('p.product_type', 'sake')
        ->where('l.quantity', '!=', 0)
        ->whereNull('l.stock_movement_id')
        ->count(),
    'receivable_statuses' => $drafts->countBy('status')->all(),
    'stock_statuses' => DB::table('stock_lot_monthly_balances')
        ->where('year', 2026)->where('month', 7)
        ->selectRaw('status, COUNT(*) AS count')
        ->groupBy('status')->pluck('count', 'status')->all(),
    'inventory_count' => $inventoryCount === null ? null : [
        'id' => $inventoryCount->id,
        'status' => $inventoryCount->status,
        'line_count' => DB::table('inventory_count_lines')->where('inventory_count_header_id', $inventoryCount->id)->count(),
        'uncounted_count' => DB::table('inventory_count_lines')
            ->where('inventory_count_header_id', $inventoryCount->id)
            ->whereNull('counted_quantity')
            ->count(),
    ],
];

$path = storage_path('app/access-migrations/reports/2026-07-close-preparation-verification.json');
file_put_contents($path, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
echo "report={$path}".PHP_EOL;
