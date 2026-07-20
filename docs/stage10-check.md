# 第10段階 進行確認

## 確認日

2026-05-25

## 10-1 ジョブ基盤

業務ジョブの状態管理基盤を追加した。

実装済み:

* `failed_jobs` テーブル
* `operation_jobs` テーブル
* `OperationJob` モデル
* `OperationJobService`
* `OperationJobException`

確認内容:

* 業務ジョブの種別、対象、payload、状態、開始日時、完了日時、失敗日時、失敗理由、実行理由を保存できる。
* ジョブ成功時は `completed` として記録する。
* ジョブ失敗時は `failed` としてエラーメッセージを記録する。
* Laravel標準の失敗ジョブ保存先として `failed_jobs` を使用できる。

## 10-2 帳票生成ジョブ

帳票生成を非同期ジョブから実行できる入口を追加した。

実装済み:

* `RunReportGenerationJob`

対象帳票:

* 出荷伝票帳票
* 請求書帳票
* 酒税申告帳票
* 消費税申告帳票
* 売掛月次残高帳票
* 在庫帳票
* ロット別在庫帳票

方針:

* ジョブは既存の帳票Serviceを呼び出す。
* ジョブ内に帳票生成ロジック、税計算、在庫計算、売掛計算を重複実装しない。
* 実行履歴は `operation_jobs` に保存する。

## 10-3 月次集計ジョブ

月次集計ドラフト作成を非同期ジョブから実行できる入口を追加した。

実装済み:

* `RunMonthlyAggregationJob`

対象集計:

* 酒税月次申告ドラフト
* 消費税月次申告ドラフト
* 売掛月次残高ドラフト
* 在庫月次残高ドラフト

方針:

* ジョブは既存の月次集計Serviceを呼び出す。
* ジョブ内に集計ロジックを重複実装しない。
* 実行履歴は `operation_jobs` に保存する。

## 10-4 締め処理ジョブ

月次確定、月次締めを非同期ジョブから実行できる入口を追加した。

実装済み:

* `RunMonthlyClosingJob`

対象処理:

* 在庫月次残高確定
* 酒税月次申告確定
* 消費税月次申告確定
* 売掛月次残高確定
* 売掛月次残高締め

方針:

* ジョブは既存の確定、締めServiceを呼び出す。
* ジョブ内に状態遷移、締め制御、集計ロジックを重複実装しない。
* 実行履歴は `operation_jobs` に保存する。

## 10-5 ジョブ失敗時の再実行制御

同一対象の重複実行防止と、失敗ジョブの再実行元記録を追加した。

実装済み:

* `operation_jobs.idempotency_key`
* `operation_jobs.retry_of_operation_job_id`
* `OperationJobService::retry`
* `operation_job.duplicate_rejected` 監査ログ
* `operation_job.retried` 監査ログ

確認内容:

* 同一ジョブ種別、同一対象の実行中ジョブがある場合、重複実行を拒否できる。
* 重複実行拒否を監査ログへ保存できる。
* 失敗済みジョブを再実行する場合、再実行元ジョブIDと試行回数を保存できる。
* 失敗済み以外のジョブを再実行元にしてはならない。

## 10-6 バッチ監査ログ

業務ジョブの開始、完了、失敗、再実行、重複拒否を監査ログへ保存する。

実装済み:

* `operation_job.started`
* `operation_job.completed`
* `operation_job.failed`
* `operation_job.retried`
* `operation_job.duplicate_rejected`

方針:

* バッチ監査ログは `operation_jobs` を対象として保存する。
* 監査ログにはジョブ種別、対象、試行回数、失敗理由、実行理由を含める。

## 10-7 運用コマンド

CLIからジョブを投入または同期実行できる運用コマンドを追加した。

実装済み:

* `aikura:report`
* `aikura:monthly-aggregation`
* `aikura:monthly-closing`
* `aikura:report-retention-check`

方針:

* コマンドはJobを呼び出す入口とし、業務計算を直接実装しない。
* `--sync` 指定時は同期実行し、未指定時はQueueへ投入する。

## 10-8 ファイル保全チェックジョブ

帳票ファイル保全検査をジョブとして実行できる入口を追加した。

実装済み:

* `RunReportExportRetentionCheckJob`

確認内容:

* `report_exports` の実ファイル存在、ファイルサイズ、SHA-256をまとめて検査できる。
* 帳票種別を指定して検査対象を絞り込める。
* 保全チェックジョブの実行履歴を `operation_jobs` に保存できる。

## テスト結果

10-1から10-8のFeature Test:

```powershell
docker compose run --rm app php artisan test tests/Feature/OperationJobTest.php
```

結果:

* `8 passed`

## 10-9 第10段階全体整合チェック

第10段階の実装、テスト、マニフェストの整合を確認した。

確認内容:

* `RunReportGenerationJob` は既存の帳票Serviceを呼び出す入口に限定されている。
* `RunMonthlyAggregationJob` は既存の月次集計Serviceを呼び出す入口に限定されている。
* `RunMonthlyClosingJob` は既存の確定、締めServiceを呼び出す入口に限定されている。
* `RunReportExportRetentionCheckJob` は既存の帳票ファイル保全Serviceを呼び出す入口に限定されている。
* Job内に税計算、酒税計算、在庫計算、売掛計算、帳票描画、状態遷移、締め制御を重複実装していない。
* `operation_jobs` はジョブ状態、対象、payload、試行回数、再実行元、理由、失敗理由を保存できる。
* `failed_jobs` はLaravel標準の失敗ジョブ保存先として存在する。
* 業務上重要なジョブ状態変化は監査ログに残る。
* 運用コマンドはJob投入または同期実行の入口に限定されている。
* `docs/manifest.md`、`docs/testing-rules.md`、`docs/stage10-check.md` は第10段階の実装内容と一致している。

判定:

* 第10段階は完了扱いでよい。
