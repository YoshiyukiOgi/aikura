<?php

/** One-time closure for B1 detail-name records with no A production_lot. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

const APPROVAL = 'batch40-b1-a-migration-complete-20260908';

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
$reportPath = storage_path('app/access-migrations/reports/'.($options['report'] ?? 'batch40-b1-a-migration-complete-20260908.json'));
ensure(! file_exists($reportPath), '既存の実行記録は上書きしません。');

try {
    DB::beginTransaction();
    DB::statement("SET LOCAL lock_timeout = '15s'");
    DB::statement('LOCK TABLE access_migration_batches, access_migration_deltas, access_migration_staging_rows, production_lots, audit_logs IN SHARE ROW EXCLUSIVE MODE');
    $rows = DB::table('access_migration_deltas as delta')
        ->leftJoin('production_lots as lot', 'lot.external_system_code', '=', DB::raw("'ITARO-DETAIL-' || delta.source_key"))
        ->where('delta.batch_id', 40)->where('delta.source_table', '商品詳細名称')->where('delta.change_type', 'changed')
        ->where('delta.apply_status', 'deferred')->whereNull('lot.id')
        ->select('delta.id', 'delta.source_key')->orderByRaw('delta.source_key::integer')->get();
    ensure($rows->count() === 44, '上書き先なしの商品詳細名称が44件と一致しません。');
    ensure(DB::table('access_migration_deltas')->where('batch_id', 40)->where('apply_status', 'deferred')->count() === 44, 'ほかに未完了の差分があります。');
    $note = APPROVAL.'; Aに対応するproduction_lotがない。B1ステージング原値を保存済みであり、業務データ作成・在庫更新の対象外として完了。';
    DB::table('access_migration_deltas')->whereIn('id', $rows->pluck('id'))->update(['apply_status' => 'skipped', 'note' => $note, 'updated_at' => now()]);
    app(AuditLogService::class)->record(new AuditLogData(
        event: 'access.delta.unmapped_detail_names_closed', targetTable: 'access_migration_batches', targetId: 40,
        beforeValues: ['deferred_detail_name_ids' => $rows->pluck('source_key')->all()],
        afterValues: ['skipped_count' => 44, 'migration_cycle' => 'complete'], reason: $note,
    ));
    $batch = DB::table('access_migration_batches')->find(40);
    $summary = json_decode($batch->delta_summary, true, flags: JSON_THROW_ON_ERROR);
    $summary['b1_a_migration_cycle'] = ['status' => 'completed', 'approval' => APPROVAL, 'completed_at' => now()->toIso8601String(), 'scope' => 'batch39_to_batch40', 'unmapped_detail_names_preserved' => 44, 'future_final_cutover_delta_required' => true];
    DB::table('access_migration_batches')->where('id', 40)->update(['delta_summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    ensure(! DB::table('access_migration_deltas')->where('batch_id', 40)->where('apply_status', 'deferred')->exists(), '保留状態が残っています。');
    $statuses = DB::table('access_migration_deltas')->where('batch_id', 40)->groupBy('apply_status')->selectRaw('apply_status,count(*) AS count')->pluck('count', 'apply_status')->all();
    $result = ['approval' => APPROVAL, 'committed' => $commit, 'batch' => 40, 'status' => 'completed', 'unmapped_detail_names_preserved' => 44, 'delta_statuses' => $statuses, 'finished_at' => now()->toIso8601String()];
    ensure(file_put_contents($reportPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) !== false, '実行記録を保存できません。');
    if ($commit) DB::commit(); else DB::rollBack();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
} catch (Throwable $exception) {
    if (DB::transactionLevel() > 0) DB::rollBack();
    fwrite(STDERR, 'ロールバック: '.$exception->getMessage()."\n");
    exit(1);
}
