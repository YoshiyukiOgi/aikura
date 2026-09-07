# 運用試験計画

## 1. 運用試験方針

運用試験は、本番開始前に実運用に近い流れで販売、出荷、請求、入金、在庫、税務、月次締め、帳票、承認、権限、バックアップ、復元を確認する工程である。

運用試験では、新機能を増やすことより、既に構築した本体業務が矛盾なく回ることを優先する。

## 2. 目的

運用試験の目的は以下とする。

* 実務担当者が、通常業務を迷わず処理できることを確認する。
* 受注から出荷、請求、入金、売掛、在庫、税務、月次締めまでの流れが接続していることを確認する。
* 取消、訂正、差戻し、締め済み期間の更新拒否など、例外処理が安全に動くことを確認する。
* 帳票出力、再発行、ファイル保全チェックが運用に耐えることを確認する。
* 承認、権限、監査ログが重要操作を追跡できることを確認する。
* バックアップ、復元、ヘルスチェック、ジョブ失敗時対応の手順が実行可能であることを確認する。

## 3. 範囲

運用試験の対象範囲は以下とする。

* 初期データ確認
* 受注
* 出荷指示
* 品出
* 出荷確定
* 請求
* 入金
* 売掛残高
* 在庫、ロット、在庫予約、ロット引当
* 酒税月次申告
* 消費税月次申告
* 在庫月次残高
* 売掛月次残高
* 月次締め
* 帳票出力、再発行、保全チェック
* 取消、訂正、調整
* 承認、権限、監査ログ
* バックアップ、復元、ヘルスチェック

第14段階の将来拡張は運用試験の必須範囲に含めない。

## 4. 実施原則

運用試験では以下を守る。

* 試験データと本番データを混在させない。
* テスト実行前に対象環境、対象年月、担当者、実施日時を記録する。
* 確定済みデータを直接更新しない。
* 失敗、差異、不明点は削除や上書きで隠さず、記録して原因を確認する。
* 月次締め、税務確定、権限変更、締め解除、取消などの重要操作は承認と監査ログの確認対象にする。
* 酒税は出荷DB内の確定税額ではなく、月次申告ドラフトと確定レコードで確認する。
* 帳票は確定済みデータから出力し、再発行時に過去帳票を上書きしない。

## 5. 判定区分

運用試験の結果は以下で判定する。

* `pass`: 想定通り処理でき、記録、帳票、監査ログも確認できた。
* `pass_with_note`: 業務継続は可能だが、手順、文言、入力補助など改善余地がある。
* `blocked`: 業務継続に支障があり、本番開始前に修正が必要である。
* `out_of_scope`: 第14段階の将来拡張など、今回の運用試験対象外である。

`blocked` が残っている状態では本番移行しない。

## 6. 記録項目

各試験シナリオでは以下を記録する。

* 試験番号
* 試験名
* 実施者
* 実施日時
* 対象環境
* 対象年月
* 使用データ
* 実施手順
* 期待結果
* 実結果
* 判定
* 発生した差異
* 対応方針
* 再試験要否

## 7. 完了条件

15-1の完了条件は以下とする。

* 運用試験の目的、範囲、実施原則、判定区分、記録項目が文書化されていること。
* 第14段階の将来拡張を運用試験の必須範囲から外していること。
* マニフェストに第15段階の位置づけが反映されていること。

## 8. 試験環境・データ方針

15-2では、運用試験で使う環境、対象年月、投入データ、バックアップ、証跡を明確にする。

## 9. 試験環境

運用試験は、原則として本番DBとは分離した検証環境で実施する。

確認項目:

* 対象環境名
* DB接続先
* アプリURL
* Docker構成
* 実施者
* 実施期間
* 対象年月
* バックアップ保存先
* 帳票ファイル保存先

本番環境を使う場合は、試験前バックアップ、試験後確認、実施責任者、実施時間帯を必ず記録する。

## 10. 対象年月

運用試験では、基本業務と月次業務の対象年月を固定する。

推奨:

* 基本業務シナリオ対象年月: `YYYY-MM`
* 月次締め対象年月: `YYYY-MM`
* 翌月繰越確認年月: `YYYY-MM + 1`

対象年月を途中で変更する場合は、変更理由、変更前後のデータ範囲、再試験要否を記録する。

## 11. データ区分

運用試験で使うデータは、以下の区分で管理する。

* `master_seed`: Seederで投入する共通マスター。
* `test_master`: 運用試験用に追加する取引先、商品、価格、税率、酒税、在庫場所。
* `opening_stock`: 試験開始時点の初期在庫。
* `opening_receivable`: 試験開始時点の初期売掛。
* `scenario_transaction`: 受注、出荷、請求、入金、在庫移動などのシナリオ内で作成する業務データ。
* `scenario_adjustment`: 差異、取消、訂正、調整を確認するためのデータ。

試験データには、後で検索できるように名称、コード、理由、備考へ運用試験用であることを残す。

## 12. 初期データ確認

運用試験前に、以下の初期データを確認する。

* 権限、ロール、ユーザー
* 取引先区分、決済売掛区分、請求サイクル
* 取引先
* 単位、商品単位
* 商品
* 価格表、価格ルール
* 消費税区分、消費税率
* 酒税区分、酒税ルール
* 在庫場所
* 製造ロット、商品ロット関連
* 初期在庫
* 初期売掛

確認観点:

* 実運用に必要なコード体系になっていること。
* 無効化すべきテスト用マスターが本番用として残っていないこと。
* 価格、税率、酒税ルールの適用開始日が対象年月と矛盾しないこと。
* 初期在庫、初期売掛はSeederへ直接混ぜず、投入ファイルまたは作業記録で管理すること。

投入されるデモデータ:

* デモ担当者、デモ承認者
* デモ卸取引先、デモ直売取引先
* デモ純米酒 720ml
* デモ吟醸酒 1800ml
* デモ甘酒 500ml
* デモきき猪口
* デモ商品価格
* デモ製造ロット
* 2026年6月開始のデモ初期在庫

ログイン確認用:

* `demo.operator@example.test`
* `demo.approver@example.test`
* 初期パスワード: `password`

### 運用試験デモ環境の一括準備

ローカルまたは検証環境では、以下の 1 コマンドで未適用マイグレーションと運用試験デモデータを投入する。
本コマンドは本番環境では実行を拒否する。

```powershell
docker compose run --rm app php artisan aikura:seed-operation-test
```

投入後は `http://localhost:8080/login` で、次のいずれかのユーザーを使ってログインできる。両ユーザーには運用試験を通して実行するための管理者権限を付与する。

| ユーザー | パスワード | 用途 |
| --- | --- | --- |
| `demo.operator@example.test` | `password` | 受注・出荷・請求・入金などの業務操作 |
| `demo.approver@example.test` | `password` | 承認操作の確認 |

一括準備では通常マスターに加え、次の画面確認データを投入する。

* `DEMO-EDIT-001`: 未出荷の編集可能な受注。`/sales-orders` で明細、数量、単価を変更できる。
* `DEMO-BILL-001`: 請求書作成前の確定出荷。`/billing` で請求書作成、確定、入金予定作成、入金登録を確認できる。
* `DEMO-ORDER-001`: 確定出荷、確定請求、入金実績を持つ通常業務フローの参照・月次・帳票確認データ。

データは架空の `DEMO` コードのみを使用し、投入を繰り返しても同じシナリオデータを重複作成しない。

注意:

* デモデータはコード、名称、備考に `DEMO` または `運用試験用` を含める。
* デモデータは実取引、実在庫、実請求には使用しない。
* 本番環境に投入する場合は、事前バックアップと承認を必須とする。
* 初期在庫は `stock_movements` の `operation_test_demo` 起点として投入される。

## 13. バックアップ方針

運用試験の前後で、DBと帳票ファイルをバックアップする。

試験前:

```powershell
docker compose exec postgres pg_dump -U aikura -d aikura -Fc -f /tmp/aikura_operation_test_before_YYYYMMDD_HHMM.dump
docker compose cp postgres:/tmp/aikura_operation_test_before_YYYYMMDD_HHMM.dump .\backups\aikura_operation_test_before_YYYYMMDD_HHMM.dump
```

試験後:

```powershell
docker compose exec postgres pg_dump -U aikura -d aikura -Fc -f /tmp/aikura_operation_test_after_YYYYMMDD_HHMM.dump
docker compose cp postgres:/tmp/aikura_operation_test_after_YYYYMMDD_HHMM.dump .\backups\aikura_operation_test_after_YYYYMMDD_HHMM.dump
```

帳票ファイル:

```powershell
Compress-Archive -Path .\storage\app\reports -DestinationPath .\backups\reports_operation_test_YYYYMMDD_HHMM.zip
```

バックアップ後に、ファイルサイズ、作成日時、保存先、作業者を記録する。

## 14. 試験前確認コマンド

試験前には以下を確認する。

```powershell
docker compose run --rm app php artisan migrate:status
docker compose run --rm app php artisan aikura:health
docker compose run --rm app php artisan aikura:report-retention-check --sync --reason="operation test precheck"
```

確認結果は運用試験結果記録へ残す。

## 15. データ取扱い禁止事項

運用試験では以下を禁止する。

* 本番データと試験データを同じ目的で混在させる。
* 確定済み伝票、請求、入金、在庫移動、税務申告、月次残高を直接更新する。
* 試験失敗データを物理削除してなかったことにする。
* 初期在庫、初期売掛を根拠記録なしに投入する。
* 税率、酒税ルール、価格ルールを対象年月と理由なしに変更する。
* 帳票ファイルを手作業で上書きする。

## 16. 15-2 完了条件

15-2の完了条件は以下とする。

* 試験環境、対象年月、データ区分、初期データ確認項目が文書化されていること。
* 試験前後のバックアップ方針が文書化されていること。
* 試験前確認コマンドが文書化されていること。
* 本番データと試験データを混在させない方針が明記されていること。

## 17. 基本業務シナリオ

15-3では、受注から入金、在庫、ロット引当までの通常業務が一気通貫で処理できることを確認する。

### 17-1. 基本業務の前提

前提:

* 試験用取引先、商品、価格、消費税、酒税、在庫場所、製造ロット、初期在庫が登録済みであること。
* 対象年月が15-2で定義した年月と一致していること。
* 試験前バックアップと試験前確認コマンドが完了していること。
* 試験用データであることが名称、コード、理由、備考から判別できること。

### 17-2. シナリオ BT-01 受注から請求まで

手順:

1. 試験用取引先に対して受注を作成する。
2. 受注明細に試験用商品、数量、希望納期を入力する。
3. 受注から出荷指示を作成する。
4. 出荷指示から品出を作成する。
5. 品出から出荷伝票ドラフトを作成する。
6. 出荷伝票ドラフトへ価格、消費税、酒税見込額が適用されていることを確認する。
7. 在庫予約またはロット引当を確認する。
8. 出荷伝票を確定する。
9. 出荷確定後に在庫移動が作成されることを確認する。
10. 出荷から請求ドラフトを作成する。
11. 請求を確定する。
12. 入金予定を確認する。

期待結果:

* 受注、出荷指示、品出、出荷、請求が状態遷移方針に沿って進むこと。
* 受注、出荷指示、品出の時点では在庫移動、請求、売掛が発生しないこと。
* 出荷確定時に在庫移動が発生すること。
* 請求確定時に請求明細、消費税、入金予定、売掛が確認できること。
* 出荷明細には確定時点の商品、単位、数量、単価、消費税率、酒税見込額のスナップショットが残ること。

記録:

* 受注番号
* 出荷指示番号
* 品出番号
* 出荷伝票番号
* 請求番号
* 対象商品、数量、ロット
* 出荷確定前後の在庫数量
* 請求確定後の入金予定額
* 判定

### 17-3. シナリオ BT-02 入金と売掛確認

手順:

1. BT-01で作成した請求に対して入金を登録する。
2. 請求への入金消込を確認する。
3. 売掛残高を確認する。
4. 一部入金、過入金、手数料、値引が必要な場合は、通常入金とは別シナリオとして記録する。

期待結果:

* 入金が請求に消し込まれること。
* 売掛残高が入金後の残高へ更新されること。
* 入金、消込、売掛残高の履歴が追跡できること。
* 入金取消や調整が必要な場合に、直接更新ではなく後続の例外シナリオへ回せること。

記録:

* 入金番号
* 消込対象請求番号
* 入金額
* 消込額
* 入金後売掛残高
* 判定

### 17-4. シナリオ BT-03 在庫、予約、ロット引当確認

手順:

1. 試験用商品の現在庫を確認する。
2. 出荷前の在庫予約を確認する。
3. ロット引当を確認する。
4. 出荷確定後の現在庫、ロット別在庫、在庫移動履歴を確認する。
5. 同一出荷行で在庫予約とロット引当がある場合、二重控除がないことを確認する。

期待結果:

* 現在庫、予約数、引当数、利用可能数が整合すること。
* ロット引当が出荷確定時の在庫移動へ接続すること。
* 在庫移動履歴が在庫の正本として追跡できること。
* 現在庫だけを直接修正する運用になっていないこと。

記録:

* 商品コード
* ロットID
* 在庫場所
* 出荷前数量
* 予約数量
* 引当数量
* 出荷後数量
* 在庫移動ID
* 判定

## 18. 月次業務シナリオ

15-4では、在庫月次、売掛月次、酒税月次申告、消費税月次申告、月次締めを確認する。

### 18-1. 月次業務の前提

前提:

* 15-3の基本業務シナリオで、対象年月に確定済み出荷、確定済み請求、入金、在庫移動が存在すること。
* 月次処理前バックアップを取得していること。
* 対象年月に同じ種類の実行中ジョブがないこと。
* 税務確定、締め操作に必要な承認要否を確認していること。

### 18-2. シナリオ MT-01 在庫月次

手順:

```powershell
docker compose run --rm app php artisan aikura:monthly-aggregation stock_lot_monthly_balance YYYY M --sync --reason="operation test stock monthly aggregation"
docker compose run --rm app php artisan aikura:monthly-closing stock_lot_monthly_balance_confirm YYYY M --sync --reason="operation test stock monthly confirm"
```

期待結果:

* 対象年月末時点の在庫月次残高ドラフトが作成されること。
* 在庫月次残高確定後、対象期間の確定済み在庫移動が締め対象として扱われること。
* 締め済み期間の在庫移動を直接変更できないこと。

記録:

* 対象年月
* 商品、ロット、在庫場所
* 月初、入庫、出庫、調整、月末数量
* ジョブID
* 判定

### 18-3. シナリオ MT-02 売掛月次

手順:

```powershell
docker compose run --rm app php artisan aikura:monthly-aggregation receivable_monthly_balance YYYY M --sync --reason="operation test receivable monthly aggregation"
docker compose run --rm app php artisan aikura:monthly-closing receivable_monthly_balance_confirm YYYY M --sync --reason="operation test receivable monthly confirm"
docker compose run --rm app php artisan aikura:monthly-closing receivable_monthly_balance_close YYYY M --sync --reason="operation test receivable monthly close"
```

期待結果:

* 顧客別の予定額、入金済額、未収額、未入金件数、一部入金件数、完了件数が保存されること。
* 確定後の売掛月次残高が固定されること。
* 締め後の対象年月に属する請求確定、請求取消、入金予定作成、入金登録、入金取消が売掛残高を変更できないこと。

記録:

* 対象年月
* 取引先
* 予定額
* 入金済額
* 未収額
* ジョブID
* 判定

### 18-4. シナリオ MT-03 酒税月次申告

手順:

```powershell
docker compose run --rm app php artisan aikura:monthly-aggregation liquor_tax_monthly_filing YYYY M --sync --reason="operation test liquor tax monthly aggregation"
docker compose run --rm app php artisan aikura:monthly-closing liquor_tax_monthly_filing_confirm YYYY M --sync --reason="operation test liquor tax monthly confirm"
```

期待結果:

* 確定済み出荷明細の酒税保存値を基に、月次酒税移出数量が集計されること。
* 酒税は出荷DB内の確定税額ではなく、月次申告ドラフトと確定レコードで確定されること。
* 確定済み月は出荷確定、出荷取消で酒税移出数量を変更できないこと。

記録:

* 対象年月
* 酒税区分
* 課税KL
* KL当たり税額
* 見込額
* 確定額
* ジョブID
* 判定

### 18-5. シナリオ MT-04 消費税月次申告

手順:

```powershell
docker compose run --rm app php artisan aikura:monthly-aggregation consumption_tax_monthly_filing YYYY M --sync --reason="operation test consumption tax monthly aggregation"
docker compose run --rm app php artisan aikura:monthly-closing consumption_tax_monthly_filing_confirm YYYY M --sync --reason="operation test consumption tax monthly confirm"
```

期待結果:

* 確定済み請求明細の消費税保存値を基に、請求日付の月で集計されること。
* 取消済み請求が集計から除外されること。
* 明細単位、請求単位の端数処理設定に沿って集計されること。
* 確定済み月は請求確定、請求取消で消費税額を変更できないこと。

記録:

* 対象年月
* 消費税区分
* 課税対象額
* 消費税額
* 確定税額
* ジョブID
* 判定

### 18-6. 月次締め後確認

手順:

```powershell
docker compose run --rm app php artisan aikura:report-retention-check --sync --reason="operation test monthly retention check"
```

確認観点:

* `/api/v1/search/closing-targets?year=YYYY&month=M` で締め対象が残っていないか確認する。
* `operation_jobs` に月次集計、確定、締めの履歴が残ること。
* `audit_logs` に実行理由と対象年月が残ること。
* 差異がある場合は元データを直接更新せず、差異理由と対応方針を記録する。

## 19. 帳票シナリオ

15-5では、確定済みデータから帳票を出力し、再発行と保全チェックを確認する。

### 19-1. 帳票シナリオの前提

前提:

* 出荷、請求、酒税申告、消費税申告、売掛月次、在庫の対象データが確定済みまたは締め済みであること。
* 帳票ファイルバックアップ方針を確認していること。
* 帳票出力理由を記録すること。

### 19-2. シナリオ RT-01 出荷、請求帳票

手順:

```powershell
docker compose run --rm app php artisan aikura:report shipment --id=SHIPMENT_ID --format=txt --sync --reason="operation test shipment report"
docker compose run --rm app php artisan aikura:report invoice --id=INVOICE_ID --format=txt --sync --reason="operation test invoice report"
```

期待結果:

* 確定済み出荷、確定済み請求から帳票が出力されること。
* 未確定データからの帳票出力が拒否されること。
* `report_exports` に帳票種別、対象ID、ファイルパス、サイズ、SHA-256、出力理由が保存されること。

### 19-3. シナリオ RT-02 税務、月次帳票

手順:

```powershell
docker compose run --rm app php artisan aikura:report liquor_tax_filing --id=LIQUOR_FILING_ID --format=txt --sync --reason="operation test liquor tax report"
docker compose run --rm app php artisan aikura:report consumption_tax_filing --id=CONSUMPTION_FILING_ID --format=txt --sync --reason="operation test consumption tax report"
docker compose run --rm app php artisan aikura:report receivable_monthly_balance --year=YYYY --month=M --format=txt --sync --reason="operation test receivable monthly report"
```

期待結果:

* 確定済み酒税月次申告から帳票が出力されること。
* 確定済み消費税月次申告から帳票が出力されること。
* 確定済みまたは締め済み売掛月次残高から帳票が出力されること。
* 未確定の税務申告、未確定の売掛月次残高からの正本帳票出力が拒否されること。

### 19-4. シナリオ RT-03 在庫帳票

手順:

```powershell
docker compose run --rm app php artisan aikura:report stock_balance --format=txt --sync --reason="operation test stock report"
docker compose run --rm app php artisan aikura:report lot_stock_balance --format=txt --sync --reason="operation test lot stock report"
```

期待結果:

* 現在庫帳票が出力されること。
* ロット別在庫帳票が出力されること。
* 対象在庫なし、対象ロット在庫なしの場合は帳票生成失敗として扱われること。

### 19-5. シナリオ RT-04 再発行と保全チェック

手順:

```powershell
docker compose run --rm app php artisan aikura:report invoice --id=INVOICE_ID --format=txt --sync --reason="operation test invoice report reissue"
docker compose run --rm app php artisan aikura:report-retention-check --sync --reason="operation test report retention check"
```

期待結果:

* 同一帳票を再発行しても過去帳票が上書きされないこと。
* 再発行ごとに別の `report_exports` と別ファイルが保存されること。
* 保全チェックでファイル存在、サイズ、SHA-256が一致すること。
* ファイル欠損、改ざん、サイズ不一致、SHA-256不一致を検出できること。

記録:

* 帳票種別
* 対象IDまたは対象年月
* 出力ファイルパス
* ファイルサイズ
* SHA-256
* 出力理由
* 再発行有無
* 保全チェック結果
* 判定

## 20. 取消・例外シナリオ

15-6では、取消、訂正、差戻し、締め済み期間の更新拒否、失敗ジョブ再実行を確認する。

### 20-1. 取消・例外の前提

前提:

* 15-3から15-5で作成した受注、出荷指示、品出、出荷、請求、入金、月次、帳票データが存在すること。
* 取消、訂正、調整の理由を記録できること。
* 取消対象に後続工程が存在する場合、逆順で取り消す方針を確認していること。

### 20-2. シナリオ EX-01 受注、出荷指示、品出取消

手順:

1. 後続工程がない受注を取消する。
2. 後続工程がある受注を直接取消し、拒否されることを確認する。
3. 品出済み出荷指示を直接取消し、拒否されることを確認する。
4. 品出を取消した後、出荷指示の品出済数量と状態が戻ることを確認する。
5. 出荷指示を取消した後、受注明細の未出荷残が戻ることを確認する。

期待結果:

* 取消理由が必須であること。
* 二重取消が拒否されること。
* 後続工程がある場合は直接取消できないこと。
* 取消しても履歴は物理削除されないこと。
* 受注、出荷指示、品出の数量状態が逆順で整合すること。

### 20-3. シナリオ EX-02 出荷、請求、入金取消

手順:

1. 未請求の出荷伝票を取消する。
2. 請求済みの出荷伝票を直接取消し、拒否されることを確認する。
3. 請求を取消し、売掛と入金予定の扱いを確認する。
4. 入金済み請求を直接取消し、拒否または承認対象になることを確認する。
5. 入金を取消し、消込と売掛残高が戻ることを確認する。

期待結果:

* 出荷取消は在庫、税、請求への影響が追跡できること。
* 請求取消は請求履歴を削除せず、取消状態と理由を保持すること。
* 入金取消は入金履歴を削除せず、消込と売掛残高が整合すること。
* 締め済み期間の出荷、請求、入金取消は拒否または承認対象になること。

### 20-4. シナリオ EX-03 締め済み期間の更新拒否

手順:

1. 15-4で月次確定または締め済みにした対象年月を確認する。
2. 対象年月に属する出荷確定、出荷取消、請求確定、請求取消、入金登録、入金取消、在庫移動変更を試す。
3. 業務拒否が返ることを確認する。

期待結果:

* 締め済み期間の業務データを直接変更できないこと。
* 拒否理由が分かること。
* 必要な場合は締め解除承認または翌月以降の調整処理へ回せること。

### 20-5. シナリオ EX-04 失敗ジョブ再実行

手順:

1. `operation_jobs` で失敗ジョブを確認する。
2. 対象、payload、失敗理由、試行回数を確認する。
3. 再実行が必要な場合、失敗した対象だけを再実行する。
4. 再実行元ジョブIDと再実行理由が記録されることを確認する。

期待結果:

* 失敗ジョブを物理削除しないこと。
* 実行中の同一ジョブ、同一対象の重複実行が拒否されること。
* 再実行理由、試行回数、成功または失敗状態が残ること。
* `audit_logs` から失敗、再実行、完了を追跡できること。

記録:

* 対象伝票番号またはジョブID
* 取消、拒否、再実行の理由
* 期待した拒否結果
* 実際の状態
* 監査ログ有無
* 判定

## 21. 承認・権限シナリオ

15-7では、重要操作の承認、自己承認禁止、権限不足時の拒否、監査ログを確認する。

### 21-1. 承認・権限の前提

前提:

* 申請者、承認者、閲覧者、権限不足ユーザーを用意する。
* 承認対象操作と対象データを決める。
* 承認理由、差戻し理由、否認理由を記録できること。

### 21-2. シナリオ AP-01 承認依頼から使用済みまで

手順:

1. 申請者で承認依頼を作成する。
2. 承認者で承認する。
3. 承認済み依頼を使って対象操作を実行する。
4. 承認依頼が使用済みになることを確認する。
5. 監査ログと承認履歴を突き合わせる。

期待結果:

* 承認依頼番号、対象種別、対象ID、申請者、承認者、理由が保存されること。
* 承認前の対象操作は拒否されること。
* 承認済みかつ未使用の依頼だけが対象操作に使えること。
* 使用済み承認依頼を再利用できないこと。

### 21-3. シナリオ AP-02 差戻し、再申請、否認

手順:

1. 承認依頼を差し戻す。
2. 申請者が理由とpayloadを修正して再申請する。
3. 承認者が承認する。
4. 別の承認依頼を否認し、否認後に承認できないことを確認する。

期待結果:

* 差戻し理由、再申請理由、否認理由が履歴に残ること。
* 差戻し済み依頼は再申請できること。
* 否認済み依頼は承認、使用できないこと。

### 21-4. シナリオ AP-03 自己承認禁止と権限不足

手順:

1. 申請者本人で自己承認を試す。
2. 権限不足ユーザーで承認依頼作成、承認、閲覧、対象操作を試す。
3. 無効ユーザーでAPI利用を試す。

期待結果:

* 自己承認が拒否されること。
* 権限不足は403で拒否されること。
* 無効ユーザーは権限を持っていてもAPI利用不可であること。
* 拒否結果が監査または操作履歴で追跡できること。

記録:

* 承認依頼番号
* 操作種別
* 対象種別、対象ID
* 申請者
* 承認者
* 承認状態
* 監査ログ有無
* 判定

## 22. バックアップ・復元シナリオ

15-8では、バックアップ、復元リハーサル、ヘルスチェック、復元後確認を実施する。

### 22-1. バックアップ確認

手順:

```powershell
docker compose exec postgres pg_dump -U aikura -d aikura -Fc -f /tmp/aikura_operation_test_restore_YYYYMMDD_HHMM.dump
docker compose cp postgres:/tmp/aikura_operation_test_restore_YYYYMMDD_HHMM.dump .\backups\aikura_operation_test_restore_YYYYMMDD_HHMM.dump
Compress-Archive -Path .\storage\app\reports -DestinationPath .\backups\reports_operation_test_restore_YYYYMMDD_HHMM.zip
```

期待結果:

* DB dumpファイルのサイズが0でないこと。
* 帳票ZIPのサイズが0でないこと。
* ファイル名に日時、環境、作業者を記録できること。
* 本番サーバー外の保存先が決まっていること。

### 22-2. 復元リハーサル

復元は既存DBを上書きする破壊的操作であるため、原則として検証環境で実施する。

手順:

```powershell
docker compose cp .\backups\aikura_operation_test_restore_YYYYMMDD_HHMM.dump postgres:/tmp/aikura_restore.dump
docker compose exec postgres dropdb -U aikura aikura
docker compose exec postgres createdb -U aikura aikura
docker compose exec postgres pg_restore -U aikura -d aikura --clean --if-exists /tmp/aikura_restore.dump
```

帳票ファイル:

```powershell
Expand-Archive -Path .\backups\reports_operation_test_restore_YYYYMMDD_HHMM.zip -DestinationPath .\storage\app -Force
```

期待結果:

* 復元対象環境が検証環境であることを確認してから実行していること。
* 復元前バックアップを取得していること。
* 復元するdumpファイルの日時、環境、作成者が確認できること。
* Web、Queue、バッチの停止要否を確認していること。

### 22-3. 復元後確認

手順:

```powershell
docker compose run --rm app php artisan migrate:status
docker compose run --rm app php artisan aikura:health
docker compose run --rm app php artisan aikura:report-retention-check --sync --reason="operation test restore verification"
```

確認観点:

* マイグレーションが全て `Ran` であること。
* ヘルスチェックが `ok` であること。
* `report_exports` と実ファイルが一致すること。
* 月次締め、税務確定、承認状態、帳票再発行履歴が復元後も追跡できること。

記録:

* バックアップファイル名
* 復元対象環境
* 復元実施者
* 復元開始、終了時刻
* 復元後確認結果
* 判定

## 23. 運用試験結果記録

15-9では、試験結果、差異、対応方針、再試験要否を記録する。

### 23-1. 結果記録テンプレート

各シナリオは以下の形式で記録する。

```text
試験番号:
試験名:
実施者:
実施日時:
対象環境:
対象年月:
使用データ:
実施手順:
期待結果:
実結果:
判定: pass / pass_with_note / blocked / out_of_scope
発生した差異:
対応方針:
担当者:
期限:
再試験要否:
再試験結果:
証跡:
```

### 23-2. 差異管理

差異は以下に分類する。

* `data_issue`: 試験データ、初期データ、マスター設定の問題。
* `procedure_issue`: 手順、説明、入力順、運用ルールの問題。
* `system_issue`: 実装、計算、状態遷移、権限、帳票、ジョブの問題。
* `out_of_scope`: 第14段階の将来拡張など、今回対象外の要望。

`system_issue` かつ業務継続に支障があるものは `blocked` とする。

### 23-3. 再試験方針

再試験が必要な場合は、以下を記録する。

* 修正内容
* 修正対象ファイルまたは手順
* 再試験対象シナリオ
* 再試験実施者
* 再試験日時
* 再試験結果

差異を修正しても、関連する月次、帳票、承認、監査ログへ影響がある場合は、関連シナリオも再試験する。

## 24. 本番移行判定

15-10では、`blocked` が残っていないことを確認し、本番移行可否を判定する。

### 24-1. 本番移行判定条件

本番移行可とする条件:

* 15-1から15-9までの運用試験結果が記録されていること。
* `blocked` が0件であること。
* `pass_with_note` が残る場合、担当者、期限、本番後対応可否が明記されていること。
* 初期データ、初期在庫、初期売掛、実運用ロール、バックアップ保存先が確定していること。
* 月次、税務、帳票、承認、権限、バックアップ、復元の重要シナリオが `pass` または本番後対応可能な `pass_with_note` であること。
* 第14段階の将来拡張要望が本番移行条件に混入していないこと。

### 24-2. 本番移行不可条件

以下がある場合は本番移行不可とする。

* `blocked` が残っている。
* 確定済みデータを直接更新しないと業務継続できない。
* 税額、酒税、売掛、在庫、月次残高の不整合原因が未確認である。
* 帳票が確定済みデータから出力できない。
* 重要操作の承認、権限、監査ログが追跡できない。
* バックアップまたは復元後確認が失敗している。
* 本番データと試験データの分離ができていない。

### 24-3. 判定記録

本番移行判定は以下を記録する。

```text
判定日:
判定者:
対象環境:
対象バージョン:
総シナリオ数:
pass件数:
pass_with_note件数:
blocked件数:
out_of_scope件数:
本番移行判定: 可 / 不可 / 条件付き可
条件付き可の場合の条件:
残課題:
本番移行予定日:
最終バックアップ予定:
承認者:
```

`条件付き可` は、業務継続に支障がなく、税務、在庫、売掛、帳票、承認、バックアップ、復元に影響しない軽微な改善に限る。
