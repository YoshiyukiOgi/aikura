# 本番運用準備手順

## 1. 初期データ投入手順

初期データ投入は、DBマイグレーション完了後にSeederを実行して行う。

前提:

* `.env` のDB接続先が本番または検証環境を指していること。
* `APP_KEY` が設定済みであること。
* DBのバックアップ取得後に実行すること。
* 本番環境では作業前に対象DB名、接続先、実行者、作業日時を記録すること。

推奨手順:

```powershell
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan db:seed --class=FoundationPermissionSeeder --force
docker compose run --rm app php artisan db:seed --class=CustomerMasterSeeder --force
docker compose run --rm app php artisan db:seed --class=ProductUnitMasterSeeder --force
docker compose run --rm app php artisan db:seed --class=PriceMasterSeeder --force
docker compose run --rm app php artisan db:seed --class=TaxMasterSeeder --force
docker compose run --rm app php artisan db:seed --class=LiquorTaxMasterSeeder --force
docker compose run --rm app php artisan db:seed --class=StockLocationSeeder --force
docker compose run --rm app php artisan db:seed --class=ShipmentMasterSeeder --force
```

一括投入する場合:

```powershell
docker compose run --rm app php artisan db:seed --force
```

投入対象:

* 権限、管理者ロール
* 取引先区分、決済売掛区分、請求サイクル
* 単位、商品単位
* 価格表
* 消費税区分、消費税率
* 酒税区分、酒税ルール
* 在庫場所
* 出荷関連マスター

投入後確認:

```powershell
docker compose run --rm app php artisan migrate:status
docker compose run --rm app php artisan test tests/Feature/AuthorizationFoundationTest.php
docker compose run --rm app php artisan test tests/Feature/CustomerMasterTest.php tests/Feature/ProductUnitMasterTest.php tests/Feature/PriceMasterTest.php
docker compose run --rm app php artisan test tests/Feature/ConsumptionTaxRateTest.php tests/Feature/LiquorTaxRuleTest.php tests/Feature/StockLocationTest.php
```

注意:

* Seederは既存レコードを更新するものと、新規作成するものが混在する。事前に差分確認する。
* 実取引先、実商品、初期在庫、初期売掛はSeederへ直接混ぜず、別途投入ファイルと承認済み作業記録で管理する。
* 価格、税率、酒税ルールの変更は、承認フロー対象として理由を保存する。

## 2. バックアップ手順

バックアップ対象:

* PostgreSQL DB
* `storage/app/reports` 配下の固定保存帳票
* `.env` など環境設定
* `docker-compose.yml`、Nginx設定、運用手順書

DBバックアップ例:

```powershell
docker compose exec postgres pg_dump -U aikura -d aikura -Fc -f /tmp/aikura_YYYYMMDD_HHMM.dump
docker compose cp postgres:/tmp/aikura_YYYYMMDD_HHMM.dump .\backups\aikura_YYYYMMDD_HHMM.dump
```

帳票ファイルバックアップ例:

```powershell
Compress-Archive -Path .\storage\app\reports -DestinationPath .\backups\reports_YYYYMMDD_HHMM.zip
```

バックアップ後確認:

* DB dumpファイルのサイズが0でないこと。
* 帳票ZIPの作成日時、サイズ、対象フォルダを確認すること。
* バックアップファイル名に日時、環境名、作業者を記録すること。
* バックアップファイルは本番サーバー外にも保管すること。

## 3. 復元手順

復元は既存DBを上書きするため、必ず停止時間と対象環境を確認してから実行する。

復元前確認:

* 復元対象環境が本番か検証かを確認する。
* 現在DBのバックアップを取得する。
* 復元するdumpファイルの日時、環境、作成者を確認する。
* Web、Queue、バッチを停止する。

DB復元例:

```powershell
docker compose cp .\backups\aikura_YYYYMMDD_HHMM.dump postgres:/tmp/aikura_restore.dump
docker compose exec postgres dropdb -U aikura aikura
docker compose exec postgres createdb -U aikura aikura
docker compose exec postgres pg_restore -U aikura -d aikura --clean --if-exists /tmp/aikura_restore.dump
```

帳票ファイル復元例:

```powershell
Expand-Archive -Path .\backups\reports_YYYYMMDD_HHMM.zip -DestinationPath .\storage\app -Force
```

復元後確認:

```powershell
docker compose run --rm app php artisan migrate:status
docker compose run --rm app php artisan aikura:report-retention-check --sync --reason="restore verification"
docker compose run --rm app php artisan test tests/Feature/HealthCheckTest.php
```

注意:

* `dropdb` は破壊的操作であり、実行前に必ず対象DB名とバックアップ済みであることを確認する。
* 復元後は帳票ファイル保全チェックで `report_exports` と実ファイルの一致を確認する。
* 復元後に月次締め、税務確定、承認状態の整合を確認する。

## 4. 権限ロール運用確認

初期状態では `FoundationPermissionSeeder` により、全権限を持つ `admin` ロールを作成する。

確認手順:

```powershell
docker compose run --rm app php artisan db:seed --class=FoundationPermissionSeeder --force
docker compose run --rm app php artisan test tests/Feature/AuthorizationFoundationTest.php
docker compose run --rm app php artisan test tests/Feature/ApiPermissionMiddlewareTest.php
```

運用ルール:

* 本番では日常利用ユーザーに `admin` を常用させない。
* 担当者ロール、閲覧者ロール、承認者ロールなどは運用開始前に別途作成する。
* 権限付与、権限剥奪、ロール変更は `role_permission_change` の承認対象とする。
* 無効化したユーザーは権限を持っていてもAPI利用不可とする。
* 退職、異動、担当変更時はユーザー無効化と承認履歴を残す。

最低限確認する権限:

* `sales_order.*`
* `shipment_instruction.*`
* `shipment_pick.*`
* `shipment.*`
* `inventory.*`
* `billing.*`
* `tax.*`
* `monthly_closing.*`
* `search.view`
* `approval.*`
* `audit_log.view`

## 5. 月次処理リハーサル

月次処理リハーサルは、本番締め前に在庫、売掛、酒税、消費税の集計、確定、締め操作を一通り確認する作業である。

前提:

* 対象年月、対象環境、作業者、実行理由を作業記録へ残す。
* リハーサル前にDBと帳票ファイルのバックアップを取得する。
* 同じ対象年月、同じ処理種別の月次ジョブを並行実行しない。
* 税額、在庫、売掛の差異が出た場合は、元データを直接更新せず、差異理由と調整方法を記録する。
* 本番で確定または締めを行う場合は、必要な承認が完了していることを確認する。

集計リハーサル例:

```powershell
docker compose run --rm app php artisan aikura:monthly-aggregation stock_lot_monthly_balance 2026 6 --sync --reason="monthly rehearsal stock aggregation"
docker compose run --rm app php artisan aikura:monthly-aggregation receivable_monthly_balance 2026 6 --sync --reason="monthly rehearsal receivable aggregation"
docker compose run --rm app php artisan aikura:monthly-aggregation liquor_tax_monthly_filing 2026 6 --sync --reason="monthly rehearsal liquor tax aggregation"
docker compose run --rm app php artisan aikura:monthly-aggregation consumption_tax_monthly_filing 2026 6 --sync --reason="monthly rehearsal consumption tax aggregation"
```

確定、締めリハーサル例:

```powershell
docker compose run --rm app php artisan aikura:monthly-closing stock_lot_monthly_balance_confirm 2026 6 --sync --reason="monthly rehearsal stock confirm"
docker compose run --rm app php artisan aikura:monthly-closing receivable_monthly_balance_confirm 2026 6 --sync --reason="monthly rehearsal receivable confirm"
docker compose run --rm app php artisan aikura:monthly-closing receivable_monthly_balance_close 2026 6 --sync --reason="monthly rehearsal receivable close"
docker compose run --rm app php artisan aikura:monthly-closing liquor_tax_monthly_filing_confirm 2026 6 --sync --reason="monthly rehearsal liquor tax confirm"
docker compose run --rm app php artisan aikura:monthly-closing consumption_tax_monthly_filing_confirm 2026 6 --sync --reason="monthly rehearsal consumption tax confirm"
```

確認観点:

* `/api/v1/search/closing-targets?year=2026&month=6` で対象年月の締め対象が残っていないか確認する。
* `operation_jobs` にジョブ種別、対象年月、実行理由、成功または失敗状態が記録されていることを確認する。
* `audit_logs` に月次集計、確定、締めの操作履歴が残ることを確認する。
* 酒税は出荷DBへ直接確定税額を持たせず、月次申告ドラフトと確定レコードで確認する。
* 消費税は明細単位、請求単位の端数処理設定に沿って月次申告ドラフトへ集計されることを確認する。

確認テスト:

```powershell
docker compose run --rm app php artisan test tests/Feature/OperationJobTest.php
docker compose run --rm app php artisan test tests/Feature/MonthlyClosingApiTest.php
docker compose run --rm app php artisan test tests/Feature/TaxApiTest.php
docker compose run --rm app php artisan test tests/Feature/ConfirmStockMonthlyBalanceTest.php tests/Feature/ConfirmReceivableMonthlyBalanceTest.php
docker compose run --rm app php artisan test tests/Feature/ConfirmLiquorTaxMonthlyFilingTest.php tests/Feature/ConfirmConsumptionTaxMonthlyFilingTest.php
```

## 6. 帳票出力リハーサル

帳票出力リハーサルは、確定済みデータから帳票を出力し、ファイル保全と再発行ルールを確認する作業である。

前提:

* 対象の出荷、請求、酒税申告、消費税申告、売掛月次、在庫データが確定済みであること。
* 帳票出力前に `storage/app/reports` のバックアップ方針を確認すること。
* 再発行時は既存帳票を上書きせず、新しい `report_exports` とファイルとして保存すること。

出力リハーサル例:

```powershell
docker compose run --rm app php artisan aikura:report shipment --id=1 --format=txt --sync --reason="report rehearsal shipment"
docker compose run --rm app php artisan aikura:report invoice --id=1 --format=txt --sync --reason="report rehearsal invoice"
docker compose run --rm app php artisan aikura:report liquor_tax_filing --id=1 --format=txt --sync --reason="report rehearsal liquor tax"
docker compose run --rm app php artisan aikura:report consumption_tax_filing --id=1 --format=txt --sync --reason="report rehearsal consumption tax"
docker compose run --rm app php artisan aikura:report receivable_monthly_balance --year=2026 --month=6 --format=txt --sync --reason="report rehearsal receivable monthly"
docker compose run --rm app php artisan aikura:report stock_balance --format=txt --sync --reason="report rehearsal stock"
docker compose run --rm app php artisan aikura:report lot_stock_balance --format=txt --sync --reason="report rehearsal lot stock"
docker compose run --rm app php artisan aikura:report-retention-check --sync --reason="report rehearsal retention check"
```

確認観点:

* `report_exports` に帳票種別、対象IDまたは対象年月、ファイルパス、ファイルサイズ、ハッシュ、実行理由が記録されること。
* `storage/app/reports` 配下に実ファイルが存在し、サイズが0でないこと。
* 再発行しても過去帳票が上書きされないこと。
* 保全チェックでDB上の帳票記録と実ファイルの不一致を検出できること。
* 税務、請求、在庫に関する帳票は、未確定データを正本帳票として出力しないこと。

確認テスト:

```powershell
docker compose run --rm app php artisan test tests/Feature/GenerateShipmentReportTest.php tests/Feature/GenerateInvoiceReportTest.php
docker compose run --rm app php artisan test tests/Feature/GenerateLiquorTaxFilingReportTest.php tests/Feature/GenerateConsumptionTaxFilingReportTest.php
docker compose run --rm app php artisan test tests/Feature/GenerateReceivableMonthlyBalanceReportTest.php tests/Feature/GenerateInventoryReportTest.php
docker compose run --rm app php artisan test tests/Feature/ReportExportRetentionTest.php tests/Feature/OperationJobTest.php
```

## 7. 運用手順書

本番運用では、日次、月次、障害時、承認時、復元時の手順を分けて扱う。

日次確認:

```powershell
docker compose run --rm app php artisan aikura:health
docker compose run --rm app php artisan aikura:report-retention-check --sync --reason="daily retention check"
```

日次確認観点:

* `/api/v1/search/pending` で未処理の受注、出荷、請求、入金、ジョブを確認する。
* `/api/v1/search/review-required` で失敗ジョブ、失敗帳票、取消済み伝票、取消済み入金を確認する。
* 失敗ジョブがある場合は `operation_jobs` の失敗理由、対象種別、対象ID、試行回数を確認する。
* バックアップの取得日時、ファイルサイズ、保存先を確認する。

月次運用:

* 月次処理前にDBと帳票ファイルのバックアップを取得する。
* `/api/v1/search/closing-targets?year=YYYY&month=M` で締め対象を確認する。
* 在庫、売掛、酒税、消費税の月次集計を実行する。
* 差異があれば元データを直接更新せず、調整伝票、差異理由、承認履歴で扱う。
* 必要な承認完了後に確定または締めを実行する。
* 月次帳票を出力し、帳票ファイル保全チェックを実行する。

承認運用:

* 承認依頼は `/api/v1/approval-requests` で作成、確認する。
* 取消、締め解除、価格変更、税務確定、権限変更は承認対象とする。
* 申請者本人による自己承認は行わない。
* 差し戻し後は理由を残し、修正後に再申請する。
* 承認済み操作を実行したら、承認依頼を使用済みにし、監査ログと突き合わせる。

障害時対応:

* 失敗したジョブや帳票を物理削除しない。
* `operation_jobs`、`report_exports`、`audit_logs` を確認し、対象と理由を特定する。
* 再実行は失敗した対象だけに限定し、再実行理由を残す。
* 復元が必要な場合は、復元前バックアップ、対象環境、停止時間、承認有無を確認する。
* 復元後は `migrate:status`、ヘルスチェック、帳票ファイル保全チェック、月次締め対象検索を実行する。

## 8. 本番運用準備 総合整合チェック

本番運用準備の完了前に、初期投入、バックアップ、復元、権限、月次処理、帳票、承認、監査ログの接続を横断的に確認する。

確認対象:

* 初期データ投入手順が、本番DB、検証DB、投入担当者、投入日時、投入理由を記録できる運用になっていること。
* バックアップ対象にDB、帳票ファイル、環境設定、Docker設定、運用手順書が含まれていること。
* 復元手順が破壊的操作であることを明示し、復元前バックアップと承認確認を必須としていること。
* 権限ロール変更、税率変更、酒税ルール変更、価格変更、締め解除、取消操作が承認対象として整理されていること。
* 月次処理で在庫、売掛、酒税、消費税の集計、確定、締めがそれぞれ確認対象になっていること。
* 酒税は出荷DBの確定税額ではなく、月次申告ドラフトと確定レコードで確定する運用になっていること。
* 帳票は確定済みデータから出力し、再発行時に過去ファイルを上書きしないこと。
* 帳票ファイル保全チェックで `report_exports` と実ファイルのサイズ、ハッシュを突き合わせられること。
* 日次、月次、障害時、承認時、復元時の確認先が手順書に残っていること。

総合確認コマンド:

```powershell
docker compose run --rm app php artisan migrate:status
docker compose run --rm app php artisan aikura:health
docker compose run --rm app php artisan aikura:report-retention-check --sync --reason="operation readiness final check"
```

総合確認テスト:

```powershell
docker compose run --rm app php artisan test tests/Feature/AuthorizationFoundationTest.php tests/Feature/ApiPermissionMiddlewareTest.php
docker compose run --rm app php artisan test tests/Feature/OperationJobTest.php tests/Feature/MonthlyClosingApiTest.php tests/Feature/TaxApiTest.php
docker compose run --rm app php artisan test tests/Feature/ApprovalFlowApiTest.php
docker compose run --rm app php artisan test tests/Feature/GenerateShipmentReportTest.php tests/Feature/GenerateInvoiceReportTest.php tests/Feature/ReportExportRetentionTest.php
docker compose run --rm app php artisan test tests/Feature/SearchApiTest.php
```

完了条件:

* 上記コマンドとテストが成功していること。
* 第13段階の手順が `docs/operations-readiness.md`、進行確認が `docs/stage13-check.md`、設計方針が `docs/manifest.md` に一致していること。
* 本番投入前に、実データ投入ファイル、初期在庫、初期売掛、実運用ロール、バックアップ保存先を別途確定していること。
* 未確定事項が残る場合は、本番開始条件から外さず、担当者、期限、確認方法を明記すること。
