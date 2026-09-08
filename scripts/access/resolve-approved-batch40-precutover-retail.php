<?php

/** One-time classification approved by the user for 玉川小売 pre-cutover deltas. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

const APPROVAL = 'batch40-precutover-tamagawa-retail-approved-20260908';

function ensure(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$options = getopt('', ['database:', 'commit', 'approval:', 'report:']);
$database = $options['database'] ?? '';
$commit = array_key_exists('commit', $options);
ensure($database === 'aikura', 'Aの業務DB aikura を明示してください。');
ensure(($options['approval'] ?? '') === APPROVAL, '承認識別子が一致しません。');
config(['database.connections.pgsql.database' => $database]);
DB::purge('pgsql');
$reportPath = storage_path('app/access-migrations/reports/'.($options['report'] ?? 'batch40-precutover-retail-resolution-20260908.json'));
ensure(! file_exists($reportPath), '既存の実行記録は上書きしません。');

try {
    DB::beginTransaction();
    DB::statement("SET LOCAL lock_timeout = '15s'");
    DB::statement('LOCK TABLE access_migration_batches, access_migration_deltas, access_migration_staging_rows, opening_receivable_balances, audit_logs IN SHARE ROW EXCLUSIVE MODE');

    $rows = DB::table('access_migration_deltas as delta')
        ->leftJoin('access_migration_staging_rows as current', 'current.id', '=', 'delta.current_staging_row_id')
        ->leftJoin('access_migration_staging_rows as baseline', 'baseline.id', '=', 'delta.baseline_staging_row_id')
        ->where('delta.batch_id', 40)
        ->whereIn('delta.source_table', ['出荷伝票・取引先', '出荷伝票・商品'])
        ->where('delta.change_type', '<>', 'unchanged')
        ->selectRaw("delta.*, COALESCE(current.payload, baseline.payload) AS source_payload")
        ->get()
        ->filter(function (object $row): bool {
            $payload = json_decode($row->source_payload, true, flags: JSON_THROW_ON_ERROR);

            return in_array((string) ($payload['伝票番号'] ?? ''), ['88729', '91110', '92397'], true);
        })
        ->values();
    ensure($rows->count() === 61, '開始前差分の対象件数が61件と一致しません。');
    ensure($rows->every(fn (object $row): bool => $row->apply_status === 'deferred'), '開始前差分に保留以外の状態があります。');

    $summary = $rows->groupBy(fn (object $row): string => (json_decode($row->source_payload, true)['伝票番号'] ?? '').'|'.$row->source_table.'|'.$row->change_type)
        ->map(fn ($group): int => $group->count())->all();
    $expectedSummary = [
        '88729|出荷伝票・取引先|changed' => 1,
        '88729|出荷伝票・商品|deleted' => 1,
        '88729|出荷伝票・商品|new' => 1,
        '91110|出荷伝票・商品|deleted' => 11,
        '91110|出荷伝票・商品|new' => 11,
        '92397|出荷伝票・商品|deleted' => 18,
        '92397|出荷伝票・商品|new' => 18,
    ];
    ksort($summary);
    ksort($expectedSummary);
    ensure($summary === $expectedSummary, '開始前差分の伝票内訳が承認時と異なります。');
    $opening = DB::table('opening_receivable_balances')->where('customer_id', 56)->where('as_of_date', '2026-07-01')->sole();
    ensure($opening->status === 'reconciled' && bccomp($opening->opening_balance_amount, '9771511.00', 2) === 0, '玉川小売の開始売掛が承認時と異なります。');
    ensure(! DB::table('shipment_headers')->whereIn('legacy_access_document_number', ['88729', '91110', '92397'])->exists(), '開始前伝票がAの業務出荷に存在します。');
    ensure(! DB::table('access_receivable_ledger_entries')->where('customer_id', 56)->where('entry_date', '<', '2026-07-01')->exists(), '開始前の玉川小売台帳がAに存在します。');

    $note = APPROVAL.'; 玉川小売は過去から実入金処理を行わない小売部門。開始前61差分はB1原値のみ保全し、開始売掛・請求・在庫・入金へ反映しない。伝票88729の税込1,848円は内部台帳上の過去売上訂正。';
    DB::table('access_migration_deltas')->whereIn('id', $rows->pluck('id'))->update(['apply_status' => 'skipped', 'note' => $note, 'updated_at' => now()]);
    app(AuditLogService::class)->record(new AuditLogData(
        event: 'access.delta.precutover_retail_excluded',
        targetTable: 'access_migration_batches',
        targetId: 40,
        beforeValues: ['delta_ids' => $rows->pluck('id')->all(), 'apply_status' => 'deferred', 'opening_balance_amount' => $opening->opening_balance_amount],
        afterValues: ['apply_status' => 'skipped', 'business_update' => false, 'opening_balance_amount' => $opening->opening_balance_amount, 'documents' => ['88729', '91110', '92397']],
        reason: $note,
    ));
    $batch = DB::table('access_migration_batches')->find(40);
    $deltaSummary = json_decode($batch->delta_summary, true, flags: JSON_THROW_ON_ERROR);
    $deltaSummary['approved_pre_cutover_retail_exclusion'] = [
        'approval' => APPROVAL,
        'customer_source_id' => '33',
        'customer_code' => 'ITARO-C-0033',
        'documents' => ['88729', '91110', '92397'],
        'rows' => 61,
        'financial_correction' => '1848.00',
        'opening_receivable_updated' => false,
        'resolved_at' => now()->toIso8601String(),
    ];
    DB::table('access_migration_batches')->where('id', 40)->update(['delta_summary' => json_encode($deltaSummary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'updated_at' => now()]);

    $state = DB::table('access_migration_deltas')->whereIn('id', $rows->pluck('id'))->groupBy('apply_status')->selectRaw('apply_status, count(*) AS count')->pluck('count', 'apply_status')->all();
    ensure($state === ['skipped' => 61], '開始前差分の分類更新に失敗しました。');
    ensure(bccomp(DB::table('opening_receivable_balances')->where('id', $opening->id)->value('opening_balance_amount'), '9771511.00', 2) === 0, '開始売掛が変更されています。');
    $result = ['approval' => APPROVAL, 'committed' => $commit, 'rows' => 61, 'documents' => ['88729', '91110', '92397'], 'opening_receivable_updated' => false, 'opening_balance_amount' => '9771511.00', 'finished_at' => now()->toIso8601String()];
    ensure(file_put_contents($reportPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) !== false, '実行記録を保存できません。');
    if ($commit) {
        DB::commit();
    } else {
        DB::rollBack();
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
} catch (Throwable $exception) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    fwrite(STDERR, 'ロールバック: '.$exception->getMessage()."\n");
    exit(1);
}
