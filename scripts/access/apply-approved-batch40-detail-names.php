<?php

/** One-time update approved by the user: B1 batch40 商品詳細名称 → existing A lots. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

const APPROVAL = 'batch40-detail-names-approved-20260908';
const BATCH = 40;

function ensure(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$options = getopt('', ['database:', 'commit', 'approval:', 'report:']);
$database = $options['database'] ?? '';
$commit = array_key_exists('commit', $options);
ensure(in_array($database, ['aikura', 'aikura_detail_names_verify_20260908'], true), '対象DBを明示してください。');
ensure(($options['approval'] ?? '') === APPROVAL, '承認識別子が一致しません。');
config(['database.connections.pgsql.database' => $database]);
DB::purge('pgsql');

$reportPath = storage_path('app/access-migrations/reports/'.($options['report'] ?? "batch40-detail-names-$database-result.json"));
ensure(! file_exists($reportPath), '既存の実行記録は上書きしません。');
$result = ['approval' => APPROVAL, 'database' => $database, 'commit_requested' => $commit, 'started_at' => date(DATE_ATOM)];

try {
    DB::beginTransaction();
    DB::statement("SET LOCAL lock_timeout = '15s'");
    DB::statement('LOCK TABLE production_lots, access_migration_deltas, access_migration_staging_rows, access_migration_mappings, audit_logs IN SHARE ROW EXCLUSIVE MODE');

    $rows = DB::table('access_migration_deltas as delta')
        ->join('access_migration_staging_rows as before', 'before.id', '=', 'delta.baseline_staging_row_id')
        ->join('access_migration_staging_rows as after', 'after.id', '=', 'delta.current_staging_row_id')
        ->leftJoin('production_lots as lot', 'lot.external_system_code', '=', DB::raw("'ITARO-DETAIL-' || delta.source_key"))
        ->where('delta.batch_id', BATCH)
        ->where('delta.source_table', '商品詳細名称')
        ->where('delta.change_type', 'changed')
        ->orderByRaw('delta.source_key::integer')
        ->selectRaw("delta.id AS delta_id, delta.source_key, delta.apply_status, after.id AS staging_id, after.payload_sha256, before.payload->>'商品詳細名称' AS before_name, after.payload->>'商品詳細名称' AS after_name, lot.*")
        ->get();
    ensure($rows->count() === 71, '商品詳細名称の対象件数が71件と一致しません。');
    $targets = $rows->filter(fn (object $row): bool => $row->id !== null)->values();
    $missing = $rows->filter(fn (object $row): bool => $row->id === null)->values();
    ensure($targets->count() === 27 && $missing->count() === 44, 'Aロットへの対応件数が承認時と異なります。');

    $updated = [];
    foreach ($targets as $row) {
        ensure($row->apply_status === 'deferred', "商品詳細{$row->source_key}は保留状態ではありません。");
        ensure($row->legacy_lot_text === $row->before_name, "商品詳細{$row->source_key}のロット名称が承認時の原値と異なります。");
        $suffix = ' / '.$row->before_name;
        ensure(str_ends_with((string) $row->display_name, $suffix), "商品詳細{$row->source_key}の表示名が安全に置換できません。");
        ensure(str_contains((string) $row->search_key, $row->before_name), "商品詳細{$row->source_key}の検索文字列が安全に置換できません。");

        $after = [
            'legacy_lot_text' => $row->after_name,
            'display_name' => mb_substr(mb_substr((string) $row->display_name, 0, -mb_strlen($suffix)).' / '.$row->after_name, 0, 160),
            'search_key' => str_replace($row->before_name, $row->after_name, (string) $row->search_key),
            'updated_at' => now(),
        ];
        DB::table('production_lots')->where('id', $row->id)->update($after);
        $newLot = DB::table('production_lots')->find($row->id);
        app(AuditLogService::class)->record(new AuditLogData(
            event: 'access.detail_name.updated',
            targetTable: 'production_lots',
            targetId: $row->id,
            beforeValues: ['detail_id' => $row->source_key, 'legacy_lot_text' => $row->before_name, 'display_name' => $row->display_name, 'search_key' => $row->search_key],
            afterValues: ['detail_id' => $row->source_key, 'legacy_lot_text' => $newLot->legacy_lot_text, 'display_name' => $newLot->display_name, 'search_key' => $newLot->search_key],
            reason: APPROVAL.'; 利用者が「これは上書きをして」と承認。B1バッチ40の原値。',
        ));
        DB::table('access_migration_mappings')->updateOrInsert(
            ['batch_id' => BATCH, 'source_table' => '商品詳細名称', 'source_key' => $row->source_key],
            ['target_table' => 'production_lots', 'target_id' => (string) $row->id, 'action' => 'updated', 'source_payload_sha256' => $row->payload_sha256, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('access_migration_staging_rows')->where('id', $row->staging_id)->update(['status' => 'imported', 'target_table' => 'production_lots', 'target_id' => (string) $row->id, 'updated_at' => now()]);
        DB::table('access_migration_deltas')->where('id', $row->delta_id)->update(['apply_status' => 'applied', 'note' => APPROVAL.'; Aの既存ロット名称をB1原値で更新', 'updated_at' => now()]);
        $updated[] = ['detail_id' => $row->source_key, 'production_lot_id' => $row->id, 'lot_code' => $row->lot_code];
    }

    foreach ($missing as $row) {
        ensure($row->apply_status === 'deferred', "商品詳細{$row->source_key}は保留状態ではありません。");
        DB::table('access_migration_deltas')->where('id', $row->delta_id)->update(['note' => APPROVAL.'; Aに対応するproduction_lotがないため、原値のみを保持', 'updated_at' => now()]);
    }

    $verification = DB::table('access_migration_deltas as delta')
        ->join('access_migration_staging_rows as after', 'after.id', '=', 'delta.current_staging_row_id')
        ->leftJoin('production_lots as lot', 'lot.external_system_code', '=', DB::raw("'ITARO-DETAIL-' || delta.source_key"))
        ->where('delta.batch_id', BATCH)->where('delta.source_table', '商品詳細名称')->where('delta.change_type', 'changed')
        ->selectRaw("count(*) FILTER (WHERE lot.id IS NOT NULL AND (lot.legacy_lot_text IS DISTINCT FROM after.payload->>'商品詳細名称' OR delta.apply_status <> 'applied')) AS target_mismatches, count(*) FILTER (WHERE lot.id IS NULL AND delta.apply_status <> 'deferred') AS missing_status_mismatches")
        ->first();
    ensure((int) $verification->target_mismatches === 0 && (int) $verification->missing_status_mismatches === 0, '上書き後のB1-A照合に不一致があります。');
    $result += ['updated_count' => 27, 'unmapped_count' => 44, 'updated' => $updated, 'verification' => (array) $verification, 'finished_at' => date(DATE_ATOM)];
    ensure(file_put_contents($reportPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) !== false, '実行記録を保存できません。');
    if ($commit) {
        DB::commit();
    } else {
        DB::rollBack();
    }
    echo json_encode(['committed' => $commit, 'updated_count' => 27, 'unmapped_count' => 44, 'verification' => $result['verification'], 'report' => $reportPath], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
} catch (Throwable $exception) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    fwrite(STDERR, 'ロールバック: '.$exception->getMessage()."\n");
    exit(1);
}
