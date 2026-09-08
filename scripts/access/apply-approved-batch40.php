<?php

/** One-time, source-bound correction approved in docs/g3-batch40-delta-review-20260908.md. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AccessMigrationBatch;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use App\Services\ImportAccessMasters;
use App\Services\ImportAccessPrices;
use App\Services\ImportAccessShipments;
use App\Services\ImportAccessInventoryHistory;
use App\Services\ImportAccessReceivables;
use Illuminate\Support\Facades\DB;

const APPROVAL = 'batch40-approved-20260908';
const HEADER = '出荷伝票・取引先';
const LINE = '出荷伝票・商品';
const SOURCE_HASH = 'B18CA5BD31EB6A8EF64DD5DE658B605B0BE953F66A516329A00E51BFDBB33D5C';

function ensure(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function audit(string $table, int $id, array $before, array $after): void {
    app(AuditLogService::class)->record(new AuditLogData(
        event: 'access.delta.approved_correction', targetTable: $table, targetId: $id,
        beforeValues: $before, afterValues: $after,
        reason: APPROVAL.'; 利用者がこのタスクで「承認する」と回答。バッチ39→40、確定請求275円訂正を含む。',
    ));
}
function changeRow(string $table, object $row, array $changes): void {
    DB::table($table)->where('id', $row->id)->update($changes + ['updated_at'=>now()]);
    audit($table, $row->id, (array)$row, (array)DB::table($table)->find($row->id));
}
function fingerprint(string $table, string $where = 'true'): string {
    ensure((bool)preg_match('/^[a-z_]+$/', $table), '不正なテーブル名');
    return DB::selectOne("SELECT md5(COALESCE(string_agg(md5(row_to_json(t)::text), '' ORDER BY md5(row_to_json(t)::text)),'')) AS hash FROM (SELECT * FROM \"$table\" WHERE $where) t")->hash;
}
function source(string $table, string $key, int $batch = 40): object {
    $row = DB::table('access_migration_staging_rows')->where('batch_id',$batch)->where('source_table',$table)->where('source_key',$key)->first();
    ensure($row !== null, "原値欠落: $table/$key");
    return $row;
}
function payload(object $row): array { return json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR); }
function mapping(string $table, string $key, string $target, int $id, string $action = 'corrected'): void {
    $row = source($table,$key);
    DB::table('access_migration_mappings')->updateOrInsert(['batch_id'=>40,'source_table'=>$table,'source_key'=>$key],[
        'target_table'=>$target,'target_id'=>(string)$id,'action'=>$action,'source_payload_sha256'=>$row->payload_sha256,'created_at'=>now(),'updated_at'=>now(),
    ]);
    DB::table('access_migration_staging_rows')->where('id',$row->id)->update(['status'=>'imported','target_table'=>$target,'target_id'=>(string)$id,'updated_at'=>now()]);
}
function businessSnapshot(): array {
    $tables = DB::table('information_schema.tables')->where('table_schema','public')->where('table_type','BASE TABLE')->orderBy('table_name')->pluck('table_name');
    $result=[];
    foreach($tables as $table) {
        if(in_array($table,['access_migration_staging_rows','access_migration_deltas','audit_logs','migrations','sessions','cache','cache_locks','operation_job_runs'])) continue;
        $result[$table]=fingerprint($table);
    }
    $result['delta40']=fingerprint('access_migration_deltas','batch_id=40');
    return $result;
}

$options=getopt('', ['database:','commit','approval:','expected-before:','report:']);
$database=$options['database']??'';
$commit=array_key_exists('commit',$options);
ensure(in_array($database,['aikura','aikura_delta40_verify_20260908']), '対象DBを明示してください');
ensure(($options['approval']??'')===APPROVAL, '今回の承認記録識別子が必要です');
ensure($database!=='aikura' || isset($options['expected-before']), 'Aでは隔離DBの適用前照合ファイルが必要です');
config(['database.connections.pgsql.database'=>$database,'queue.default'=>'sync']);
DB::purge('pgsql');
$reportPath=storage_path('app/access-migrations/reports/'.($options['report']??"batch40-$database-result.json"));
ensure(!file_exists($reportPath), '既存の実行記録は上書きしません');
$result=['approval'=>APPROVAL,'database'=>$database,'commit_requested'=>$commit,'started_at'=>date(DATE_ATOM)];
try {
    DB::beginTransaction();
    DB::statement("SET LOCAL lock_timeout='15s'");
    // Prevent concurrent business writes while retaining ordinary read access.
    $tables=DB::table('information_schema.tables')->where('table_schema','public')->where('table_type','BASE TABLE')->orderBy('table_name')->pluck('table_name');
    foreach($tables as $table) DB::statement('LOCK TABLE "'.str_replace('"','""',$table).'" IN SHARE ROW EXCLUSIVE MODE');
    $batch=AccessMigrationBatch::findOrFail(40);
    ensure($batch->status==='ready_with_warnings' && $batch->baseline_batch_id===39 && strtoupper($batch->source_sha256)===SOURCE_HASH, '承認対象のバッチ状態と不一致');
    ensure(AccessMigrationBatch::findOrFail(39)->status==='delta_applied', '基準バッチ未適用');
    $counts=DB::table('access_migration_deltas')->where('batch_id',40)->groupBy('change_type')->selectRaw('change_type,count(*) as n')->pluck('n','change_type')->all();
    ensure($counts==['new'=>739,'changed'=>920,'deleted'=>50,'unchanged'=>454317], '差分件数が承認時と異なります');
    ensure(!DB::table('access_migration_deltas')->where('batch_id',40)->where('apply_status','<>','planned')->exists(),'差分の一部が処理済みです');
    $result['before']=businessSnapshot();
    if(isset($options['expected-before'])) {
        $expected=json_decode(file_get_contents($options['expected-before']),true,flags:JSON_THROW_ON_ERROR);
        $diff=array_keys(array_diff_assoc($result['before'],$expected['before']));
        ensure($diff===[], '隔離DBで検証した状態から変更があります: '.implode(',',$diff));
    }
    $protected=[];
    foreach(['stock_movements','stock_lot_monthly_balances','opening_receivable_balances','liquor_tax_monthly_filings','consumption_tax_monthly_filings'] as $table) $protected[$table]=fingerprint($table);
    $protected['other_invoices']=fingerprint('invoice_headers','id<>41');
    $protected['other_monthly']=fingerprint('receivable_monthly_balances','id<>1028');
    $originalLotMax=(int)DB::table('production_lots')->max('id');
    $originalLotHash=fingerprint('production_lots',"id <= $originalLotMax");
    $result['protected_before']=$protected;
    $invoice=DB::table('invoice_headers')->find(41);
    ensure($invoice->status==='confirmed' && $invoice->invoice_number==='I-202608-000041' && bccomp($invoice->total_amount,'10115439.20',2)===0, '請求41の承認時状態と不一致');
    $schedule=DB::table('payment_schedules')->where('invoice_header_id',41)->sole();
    ensure($schedule->status==='open' && bccomp($schedule->received_amount,'0',2)===0, '回収予定に新しい入金があります');
    $monthly=DB::table('receivable_monthly_balances')->find(1028);
    ensure($monthly->status==='draft' && (int)$monthly->customer_id===(int)$invoice->customer_id, '対象売掛月次が変更されています');

    $deltas=DB::table('access_migration_deltas')->where('batch_id',40)->where('change_type','<>','unchanged')->get();
    foreach($deltas as $d) {
        foreach([['current_staging_row_id','current_payload_sha256'],['baseline_staging_row_id','baseline_payload_sha256']] as [$idColumn,$hashColumn]) {
            if($d->$idColumn!==null) {
                $raw=DB::table('access_migration_staging_rows')->where('id',$d->$idColumn)->value('payload');
                ensure(strtoupper(hash('sha256',$raw))===$d->$hashColumn, '計画後に原値が変更されています');
            }
        }
    }
    fwrite(STDOUT,"原値・請求・月次の適用前照合成功\n");
    $result['imports']['masters']=app(ImportAccessMasters::class)->import($batch);
    fwrite(STDOUT,"マスタ取込完了\n");
    $result['imports']['prices']=app(ImportAccessPrices::class)->import($batch->fresh());
    fwrite(STDOUT,"価格取込完了\n");

    // Rekey unchanged business lines; use a multiset to retain duplicated products.
    $rekey=[]; $excluded=[]; $handled=[];
    foreach(['93249','93309','93321','93395'] as $document) {
        $old=DB::table('access_migration_staging_rows')->where('batch_id',39)->where('source_table',LINE)->whereRaw("payload->>'伝票番号'=?",[$document])->orderBy('source_key')->get();
        $new=DB::table('access_migration_staging_rows')->where('batch_id',40)->where('source_table',LINE)->whereRaw("payload->>'伝票番号'=?",[$document])->orderBy('source_key')->get();
        $used=[];
        foreach($old as $o) {
            $op=payload($o); $match=null;
            foreach($new as $n) {
                if(isset($used[$n->source_key])) continue;
                $np=payload($n); $same=true;
                foreach(['商品ID','商品詳細ID','個数','単価','取引額','商品税額','消費税率','酒税','軽減率'] as $field) {
                    if(($op[$field]??null)!=($np[$field]??null)) $same=false;
                }
                if($same) { $match=$n; break; }
            }
            if($match===null) {
                ensure($document==='93321' && $o->source_key==='312183' && (int)$op['商品ID']===9999 && (float)$op['取引額']===0.0,'承認外の旧明細削除');
                continue;
            }
            $target=DB::table('shipment_lines')->where('legacy_access_line_id',$o->source_key)->sole();
            ensure(bccomp($target->quantity,(string)$op['個数'],4)===0 && bccomp($target->legacy_access_transaction_amount,(string)$op['取引額'],2)===0,'Aの旧明細に差異');
            changeRow('shipment_lines',$target,['legacy_access_line_id'=>$match->source_key]);
            mapping(LINE,$match->source_key,'shipment_lines',$target->id,'rekeyed');
            $used[$match->source_key]=true;
            $rekey[]=['id'=>$target->id,'old'=>$o->source_key,'new'=>$match->source_key,'document'=>$document];
            $handled[LINE.'/'.$o->source_key]='B1明細再採番。内部IDと請求・在庫参照を維持';
            $handled[LINE.'/'.$match->source_key]='B1明細再採番。内部IDと請求・在庫参照を維持';
        }
    }
    ensure(count($rekey)===19,'再採番対応が19件ではありません');
    $result['rekeyed']=$rekey;
    $newHeaderKeys=$deltas->where('source_table',HEADER)->where('change_type','new')->pluck('source_key')->all();
    $newLineKeys=[];
    foreach($deltas->where('source_table',LINE)->where('change_type','new') as $d) {
        $p=payload(source(LINE,$d->source_key));
        if(in_array((string)$p['伝票番号'],$newHeaderKeys,true) || $d->source_key==='313541') $newLineKeys[]=$d->source_key;
    }
    ensure(count($newHeaderKeys)===141 && count($newLineKeys)===486,'追加対象件数が承認時と不一致');
    // Only selected rows are sent to the importer; the original delta classifications stay intact.
    $correctedHeaders=DB::table('shipment_headers')->whereIn('legacy_access_document_number',['93249','93485'])->get();
    $result['imports']['shipments']=app(ImportAccessShipments::class)->import($batch->fresh(),true,'2026-07-01',[
        HEADER=>array_merge($newHeaderKeys,['93249','93485']), LINE=>$newLineKeys,
    ]);
    foreach($correctedHeaders as $header) audit('shipment_headers',$header->id,(array)$header,(array)DB::table('shipment_headers')->find($header->id));
    foreach(['312757','312982','312983'] as $key) {
        $p=payload(source(LINE,$key));
        $target=DB::table('shipment_lines')->where('legacy_access_line_id',$key)->sole();
        changeRow('shipment_lines',$target,[
            'legacy_access_detail_id'=>(string)$p['商品詳細ID'],
            'note'=>implode('; ',array_filter([trim((string)($p['摘要']??'')), 'Access商品詳細ID='.$p['商品詳細ID']])),
        ]);
        mapping(LINE,$key,'shipment_lines',$target->id);
    }
    fwrite(STDOUT,"出荷取込・19明細の参照維持完了\n");

    // Amend only the two reviewed invoice lines and retain snapshots in audit_logs.
    $removed=DB::table('shipment_lines')->where('legacy_access_line_id','312183')->sole();
    foreach(['stock_movements'=>'source_shipment_line_id','sales_return_lines'=>'source_shipment_line_id','shipment_lot_allocations'=>'shipment_line_id'] as $table=>$column) ensure(!DB::table($table)->where($column,$removed->id)->exists(),'削除対象に未承認の参照があります');
    $oldInvoiceLine=DB::table('invoice_lines')->where('shipment_line_id',$removed->id)->sole();
    ensure((int)$oldInvoiceLine->invoice_header_id===41 && bccomp($oldInvoiceLine->amount,'0',2)===0 && bccomp($oldInvoiceLine->tax_amount,'0',2)===0,'削除対象の請求明細が承認時と不一致');
    audit('invoice_lines',$oldInvoiceLine->id,(array)$oldInvoiceLine,['deleted'=>true]);
    DB::table('invoice_lines')->where('id',$oldInvoiceLine->id)->delete();
    audit('shipment_lines',$removed->id,(array)$removed,['deleted'=>true]);
    DB::table('shipment_lines')->where('id',$removed->id)->delete();
    $added=DB::table('shipment_lines')->where('legacy_access_line_id','313541')->sole();
    $line=['invoice_header_id'=>41,'shipment_header_id'=>$added->shipment_header_id,'shipment_line_id'=>$added->id,
        'line_no'=>DB::table('invoice_lines')->where('invoice_header_id',41)->max('line_no')+1,
        'product_id'=>$added->product_id,'quantity'=>$added->confirmed_quantity,'unit_price'=>$added->confirmed_unit_price,
        'amount'=>'250.00','tax_amount'=>'25.00','total_amount'=>'275.00','tax_rate'=>$added->confirmed_consumption_tax_rate,
        'note'=>APPROVAL,'created_at'=>now(),'updated_at'=>now()];
    foreach(['product_code','product_name','display_name','unit_code','unit_name','consumption_tax_category_id','consumption_tax_category_code','consumption_tax_category_name','consumption_taxability','consumption_tax_rate_id','consumption_tax_rate_effective_from'] as $field) $line[$field]=$added->{'confirmed_'.$field};
    $newInvoiceLine=DB::table('invoice_lines')->insertGetId($line);
    audit('invoice_lines',$newInvoiceLine,[],(array)DB::table('invoice_lines')->find($newInvoiceLine));
    $changes=[];
    foreach(['subtotal_amount'=>250,'tax_amount'=>25,'total_amount'=>275,'current_sales_amount'=>250,'current_tax_amount'=>25,'current_invoice_amount'=>275] as $field=>$delta) $changes[$field]=bcadd($invoice->$field,(string)$delta,2);
    changeRow('invoice_headers',$invoice,$changes);
    changeRow('payment_schedules',$schedule,['scheduled_amount'=>bcadd($schedule->scheduled_amount,'275',2),'outstanding_amount'=>bcadd($schedule->outstanding_amount,'275',2)]);
    // Recalculate this customer's July only; retain the other 121 draft rows and all closed months.
    $schedules=DB::table('payment_schedules as p')->join('invoice_headers as i','i.id','=','p.invoice_header_id')->where('p.customer_id',$invoice->customer_id)->whereNotIn('i.status',['draft','cancelled'])->whereNull('i.cancelled_at')->whereBetween('i.invoice_date',['2026-07-01','2026-07-31'])->select('p.*')->get();
    ensure($schedules->count()===1 && (int)$schedules->first()->id===(int)$schedule->id, '売掛月次の集計対象が想定と異なります');
    ensure(!DB::table('payment_allocations')->where('payment_schedule_id',$schedule->id)->exists(), '想定外の入金充当があります');
    $monthlyAmount=bcadd($schedule->scheduled_amount,'275',2);
    changeRow('receivable_monthly_balances',$monthly,['scheduled_amount'=>$monthlyAmount,'received_amount'=>'0.00','outstanding_amount'=>$monthlyAmount,'open_schedule_count'=>1,'partial_schedule_count'=>0,'closed_schedule_count'=>0,'calculated_at'=>now(),'reason'=>APPROVAL]);
    $result['invoice']=['id'=>41,'before'=>$invoice->total_amount,'after'=>$changes['total_amount'],'increase'=>'275.00'];
    $result['monthly']=['id'=>1028,'before'=>$monthly->outstanding_amount,'after'=>$monthlyAmount,'preexisting_schedule_difference'=>bcsub($schedule->scheduled_amount,$monthly->scheduled_amount,2)];

    $result['imports']['inventory_history']=app(ImportAccessInventoryHistory::class)->import($batch->fresh(),true,'2026-07-01');
    $result['imports']['receivables']=app(ImportAccessReceivables::class)->import($batch->fresh(),true,'2026-07-01');
    $paymentSource=source('入金','20551'); $pp=payload($paymentSource);
    $ledger=DB::table('access_receivable_ledger_entries')->where('legacy_access_payment_id','20551')->sole();
    ensure(bccomp($ledger->signed_amount,(string)$pp['金額'],2)===0,'入金20551の金額が異なります');
    changeRow('access_receivable_ledger_entries',$ledger,['description'=>$pp['摘要']??null,'source_payload'=>$paymentSource->payload,'source_payload_sha256'=>$paymentSource->payload_sha256]);
    $payment=DB::table('payments')->where('legacy_access_payment_id','20551')->first();
    if($payment) changeRow('payments',$payment,['note'=>implode('; ',array_filter(['Access入金履歴',"請求年月={$ledger->billing_year}-".str_pad((string)$ledger->billing_month,2,'0',STR_PAD_LEFT),$ledger->is_transfer_fee?'振込料':null,$pp['摘要']??null]))]);
    mapping('入金','20551','access_receivable_ledger_entries',$ledger->id);

    foreach($deltas as $d) {
        $note=$handled[$d->source_table.'/'.$d->source_key]??APPROVAL;
        $status='applied';
        if(in_array($d->source_table,[HEADER,LINE],true)) {
            $p=payload(source($d->source_table,$d->source_key,$d->change_type==='deleted'?39:40));
            if(in_array((string)($p['伝票番号']??''),['88729','91110','92397'],true)) {
                $status='deferred'; $note='取引開始前のため業務取込対象外。88729の1,848円差異は開始売掛へ反映せず保留; '.APPROVAL;
                $excluded[]=$d->id;
            }
        }
        if($d->source_table==='商品詳細名称') {
            $status='deferred'; $note='原値保持。業務ロットとの対応が未確定の名称変更を自動上書きしない; '.APPROVAL;
        }
        DB::table('access_migration_deltas')->where('id',$d->id)->update(['apply_status'=>$status,'note'=>$note,'updated_at'=>now()]);
    }
    ensure(count($excluded)===61,'開始前の保留件数が想定と異なります');
    DB::table('access_migration_deltas')->where('batch_id',40)->where('change_type','unchanged')->update(['apply_status'=>'skipped','note'=>'前回と同一','updated_at'=>now()]);

    // Independent B1/A comparison for every post-cutover shipment and line, not just totals.
    $mismatches=DB::selectOne("SELECT count(*) AS n FROM access_migration_staging_rows s LEFT JOIN shipment_headers a ON a.legacy_access_document_number=s.source_key WHERE s.batch_id=40 AND s.source_table='出荷伝票・取引先' AND (s.payload->>'年月日')::date >= '2026-07-01' AND (a.id IS NULL OR a.document_date <> (s.payload->>'年月日')::date OR a.legacy_access_net_amount IS DISTINCT FROM (s.payload->>'金額')::numeric OR a.legacy_access_consumption_tax_amount IS DISTINCT FROM (s.payload->>'消費税額')::numeric OR a.legacy_access_total_amount IS DISTINCT FROM (s.payload->>'合計')::numeric)")->n;
    ensure((int)$mismatches===0,'出荷ヘッダーB1-A照合不一致');
    $lineMismatches=DB::selectOne("SELECT count(*) AS n FROM access_migration_staging_rows s JOIN access_migration_staging_rows h ON h.batch_id=40 AND h.source_table='出荷伝票・取引先' AND h.source_key=s.payload->>'伝票番号' LEFT JOIN shipment_lines a ON a.legacy_access_line_id=s.source_key WHERE s.batch_id=40 AND s.source_table='出荷伝票・商品' AND (h.payload->>'年月日')::date >= '2026-07-01' AND (a.id IS NULL OR a.quantity IS DISTINCT FROM (s.payload->>'個数')::numeric OR a.legacy_access_transaction_amount IS DISTINCT FROM (s.payload->>'取引額')::numeric OR a.legacy_access_consumption_tax_amount IS DISTINCT FROM (s.payload->>'商品税額')::numeric OR a.legacy_access_detail_id IS DISTINCT FROM s.payload->>'商品詳細ID')")->n;
    ensure((int)$lineMismatches===0,'出荷明細B1-A照合不一致');
    ensure(DB::table('shipment_headers')->count()===584 && DB::table('shipment_lines')->count()===1974,'出荷の最終件数が不一致');
    ensure(fingerprint('production_lots',"id <= $originalLotMax")===$originalLotHash,'既存ロットが変更されています');
    ensure(DB::table('production_lots')->count()===322,'ロット件数が想定と異なります');
    $receiptMismatches=DB::selectOne("SELECT count(*) AS n FROM access_migration_deltas d JOIN access_migration_staging_rows s ON s.id=d.current_staging_row_id LEFT JOIN access_receivable_ledger_entries a ON a.legacy_access_payment_id=s.source_key WHERE d.batch_id=40 AND d.source_table='入金' AND d.change_type IN ('new','changed') AND (a.id IS NULL OR a.signed_amount IS DISTINCT FROM (s.payload->>'金額')::numeric OR a.entry_date IS DISTINCT FROM (s.payload->>'年月日')::date OR a.description IS DISTINCT FROM NULLIF(s.payload->>'摘要',''))")->n;
    ensure((int)$receiptMismatches===0,'入金B1-A照合不一致');
    ensure(DB::table('access_receivable_ledger_entries')->select('legacy_access_payment_id')->groupBy('legacy_access_payment_id')->havingRaw('count(*)>1')->get()->isEmpty(),'入金台帳が重複しています');
    $inventory=DB::table('non_sales_stock_operation_lines')->where('legacy_access_stock_leg_key','31997:minus')->sole();
    ensure(bccomp($inventory->quantity,'-1',4)===0 && $inventory->stock_movement_id===null,'在庫履歴1件の照合不一致');
    foreach($protected as $key=>$hash) {
        $actual=match($key){'other_invoices'=>fingerprint('invoice_headers','id<>41'),'other_monthly'=>fingerprint('receivable_monthly_balances','id<>1028'),default=>fingerprint($key)};
        ensure($actual===$hash, "保護対象が変化しました: $key");
    }
    $result['validation']=['shipment_headers'=>584,'shipment_lines'=>1974,'header_mismatches'=>(int)$mismatches,'line_mismatches'=>(int)$lineMismatches,'receipt_mismatches'=>(int)$receiptMismatches,'protected_tables_unchanged'=>true,'existing_lots_unchanged'=>true,'new_archived_lots'=>0];
    $result['delta_statuses']=DB::table('access_migration_deltas')->where('batch_id',40)->groupBy('apply_status')->selectRaw('apply_status,count(*) as n')->pluck('n','apply_status')->all();
    $result['finished_at']=date(DATE_ATOM);
    $summary=$batch->fresh()->delta_summary;
    $summary['apply']=$result['imports'];
    $summary['approved_correction']=$result;
    $batch->update(['status'=>'delta_applied','delta_summary'=>$summary,'delta_applied_at'=>now(),'completed_at'=>now()]);
    audit('access_migration_batches',40,['status'=>'ready_with_warnings'],['status'=>'delta_applied','approval'=>APPROVAL,'delta_statuses'=>$result['delta_statuses'],'validation'=>$result['validation'],'pending'=>['pre_cutover_difference'=>1848,'detail_names'=>71]]);
    $result['committed']=$commit;
    ensure(file_put_contents($reportPath,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))!==false,'実行記録を保存できません');
    if($commit) DB::commit(); else DB::rollBack();
    echo json_encode(['committed'=>$commit,'invoice'=>$result['invoice'],'monthly'=>$result['monthly'],'validation'=>$result['validation'],'delta_statuses'=>$result['delta_statuses'],'report'=>$reportPath],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
} catch(Throwable $e) {
    if(DB::transactionLevel()>0) DB::rollBack();
    fwrite(STDERR,'ロールバック: '.$e->getMessage()."\n");
    exit(1);
}
