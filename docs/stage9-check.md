# 第9段階 進行確認

## 確認日

2026-05-25

## 9-1 出荷伝票帳票基礎

確定済み出荷伝票から固定TXT帳票を生成するServiceを追加した。

実装済み:

* `GenerateShipmentReportService`
* `ShipmentReportExportException`
* `GenerateShipmentReportTest`
* `report_exports.report_type = shipment`
* `shipment_report.generated` 監査ログ

確認内容:

* 確定済み出荷伝票から帳票ファイルを生成できる。
* 帳票出力履歴に形式、保存パス、ファイルサイズ、SHA-256、出力理由を保存できる。
* 出荷確定時点のスナップショットから商品名、数量、単価、消費税、酒税見込額を出力できる。
* 未確定出荷伝票からの帳票生成は失敗する。
* 未対応形式の帳票生成は失敗する。
* 帳票生成時に監査ログが残る。

方針:

* 出荷帳票は確定済み出荷伝票だけを対象とする。
* 帳票生成時に商品マスター、価格マスター、税マスターを再解決しない。
* 発行済み帳票ファイルを再計算結果で上書きしない。

## 9-2 請求書帳票固定保存強化

請求書帳票の固定保存と再発行履歴を強化した。

実装済み:

* `GenerateInvoiceReportService` の保存パス一意化
* `GenerateInvoiceReportTest` の再発行履歴確認
* `report_exports.report_type = invoice`
* `invoice_report.generated` 監査ログ

確認内容:

* 確定済み請求から帳票ファイルを生成できる。
* 帳票出力履歴に形式、保存パス、ファイルサイズ、SHA-256、出力理由を保存できる。
* 同一請求書を再発行しても、既存ファイルを上書きせず別ファイルとして保存できる。
* 請求確定時点の明細スナップショットから商品名、数量、単価、消費税、合計を出力できる。
* 未確定請求からの帳票生成は失敗する。
* 未対応形式の帳票生成は失敗する。
* 帳票生成時に監査ログが残る。

方針:

* 請求書帳票は確定済み請求だけを対象とする。
* 帳票生成時に出荷伝票、商品マスター、価格マスター、税マスターを再解決しない。
* 再発行時も過去の発行済み帳票ファイルを上書きしない。

## 9-3 酒税申告帳票固定保存強化

酒税申告帳票の固定保存と再発行履歴を強化した。

実装済み:

* `GenerateLiquorTaxFilingReportService` の保存パス一意化
* `GenerateLiquorTaxFilingReportTest` の再発行履歴確認
* `report_exports.report_type = liquor_tax_filing`
* `liquor_tax_filing_report.generated` 監査ログ

確認内容:

* 確定済み酒税月次申告から帳票ファイルを生成できる。
* 帳票出力履歴に形式、保存パス、ファイルサイズ、SHA-256、出力理由を保存できる。
* 同一酒税月次申告を再発行しても、既存ファイルを上書きせず別ファイルとして保存できる。
* 酒税月次申告確定時点の行スナップショットから税区分、KL数量、KL当たり税額、軽減率、確定額を出力できる。
* 未確定酒税月次申告からの帳票生成は失敗する。
* 未対応形式の帳票生成は失敗する。
* 帳票生成時に監査ログが残る。

方針:

* 酒税申告帳票は確定済み酒税月次申告だけを対象とする。
* 帳票生成時に出荷伝票、商品マスター、酒税マスターを再解決しない。
* 再発行時も過去の発行済み帳票ファイルを上書きしない。

## 9-4 消費税申告帳票固定保存強化

消費税申告帳票の固定保存と再発行履歴を強化した。

実装済み:

* `GenerateConsumptionTaxFilingReportService` の保存パス一意化
* `GenerateConsumptionTaxFilingReportTest` の再発行履歴確認
* `report_exports.report_type = consumption_tax_filing`
* `consumption_tax_filing_report.generated` 監査ログ

確認内容:

* 確定済み消費税月次申告から帳票ファイルを生成できる。
* 帳票出力履歴に形式、保存パス、ファイルサイズ、SHA-256、出力理由を保存できる。
* 同一消費税月次申告を再発行しても、既存ファイルを上書きせず別ファイルとして保存できる。
* 消費税月次申告確定時点の行スナップショットから税区分、税率、課税対象額、確定税額、税込額を出力できる。
* 未確定消費税月次申告からの帳票生成は失敗する。
* 未対応形式の帳票生成は失敗する。
* 帳票生成時に監査ログが残る。

方針:

* 消費税申告帳票は確定済み消費税月次申告だけを対象とする。
* 帳票生成時に請求、商品マスター、消費税マスターを再解決しない。
* 再発行時も過去の発行済み帳票ファイルを上書きしない。

## 9-5 売掛月次残高帳票固定保存強化

売掛月次残高帳票の固定保存と再発行履歴を強化した。

実装済み:

* `GenerateReceivableMonthlyBalanceReportService` の保存パス一意化
* `GenerateReceivableMonthlyBalanceReportTest` の再発行履歴確認
* `report_exports.report_type = receivable_monthly_balance`
* `receivable_monthly_balance_report.generated` 監査ログ

確認内容:

* 確定済みまたは締め済み売掛月次残高から帳票ファイルを生成できる。
* 帳票出力履歴に形式、保存パス、ファイルサイズ、SHA-256、出力理由を保存できる。
* 同一年月の売掛月次残高帳票を再発行しても、既存ファイルを上書きせず別ファイルとして保存できる。
* 売掛月次残高確定時点または締め時点の顧客別残高スナップショットから、予定額、入金額、未回収額、件数を出力できる。
* 未確定売掛月次残高からの帳票生成は失敗する。
* 未対応形式の帳票生成は失敗する。
* 帳票生成時に監査ログが残る。

方針:

* 売掛月次残高帳票は確定済みまたは締め済み売掛月次残高だけを対象とする。
* 帳票生成時に請求、入金予定、入金データを再集計しない。
* 再発行時も過去の発行済み帳票ファイルを上書きしない。

## 9-6 在庫帳票・ロット別在庫帳票固定保存

現在在庫帳票とロット別在庫帳票の固定保存、再発行履歴、ファイル保全を追加した。

実装済み:

* `GenerateStockBalanceReportService`
* `GenerateLotStockBalanceReportService`
* `InventoryReportExportException`
* `GenerateInventoryReportTest`
* `report_exports.report_type = stock_balance`
* `report_exports.report_type = lot_stock_balance`
* `stock_balance_report.generated` 監査ログ
* `lot_stock_balance_report.generated` 監査ログ

確認内容:

* 確定済みまたは締め済み在庫移動から現在在庫帳票を生成できる。
* ロット番号を持つ確定済みまたは締め済み在庫移動からロット別在庫帳票を生成できる。
* 帳票出力履歴に形式、保存パス、ファイルサイズ、SHA-256、出力理由を保存できる。
* 同じ在庫状態で再発行しても、既存ファイルを上書きせず別ファイルとして保存できる。
* 在庫帳票には現物数量、予約数量、ロット引当数量、利用可能数量を出力できる。
* ロット別在庫帳票にはロット、商品、在庫場所、単位、現物数量、予約数量、ロット引当数量、利用可能数量を出力できる。
* 対象在庫が存在しない場合、帳票生成は失敗する。
* 未対応形式の帳票生成は失敗する。
* 帳票生成時に監査ログが残る。

方針:

* 在庫帳票は `CurrentStockBalanceService` の集計結果を根拠とする。
* ロット別在庫帳票は `LotStockBalanceService` の集計結果を根拠とする。
* 帳票生成時に在庫数量計算をControllerや帳票Serviceへ重複実装しない。
* 再発行時も過去の発行済み帳票ファイルを上書きしない。

## 9-7 再発行履歴・出力理由・ファイル保全

帳票共通の再発行履歴確認とファイル保全検査を追加した。

実装済み:

* `ReportExportRetentionService`
* `ReportExportFileVerification`
* `ReportExportRetentionException`
* `ReportExportRetentionTest`

確認内容:

* `report_exports` の保存パスにあるファイル実体が存在することを検査できる。
* 保存済みファイルサイズが `report_exports.file_size` と一致することを検査できる。
* 保存済みファイルのSHA-256が `report_exports.checksum_sha256` と一致することを検査できる。
* ファイル欠損は保全検査失敗として扱う。
* ファイル改ざんまたは不整合は保全検査失敗として扱う。
* 同一帳票種別、同一対象の再発行履歴を新しい順に取得できる。
* 再発行履歴には出力理由を残し、確認できる。

方針:

* 帳票ファイルの存在、サイズ、SHA-256は `report_exports` を正本として検査する。
* 再発行しても過去の `report_exports` とファイルを削除または上書きしない。
* ファイル保全検査は帳票種別に依存しない共通処理とする。
