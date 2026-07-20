# DB設計方針

## 1. 位置づけ

この文書は、`docs/manifest.md` に従ってDB設計の基本規約を定義する。

DB変更、migration作成、model作成、Service実装、テスト作成時はこの文書を参照する。

## 2. 基本原則

1. PostgreSQLを正規DBとする。
2. 履歴台帳を正本とし、現在値テーブルは参照性能向上のための管理値とする。
3. 確定済みデータ、締め済みデータ、発行済み帳票、監査ログは原則として破壊的更新を行わない。
4. 確定済み業務データの修正は、取消、訂正、調整、差分レコードで表現する。
5. マスタ変更によって過去伝票、過去請求、過去税額、過去酒税、過去帳票の内容が変化してはならない。

## 3. 命名規則

### 3-1. テーブル

* テーブル名は英小文字の複数形スネークケースとする。
* 例: `customers`, `products`, `shipment_headers`, `shipment_lines`

### 3-2. 主キー

* 主キーは原則として `id` とする。
* 型はLaravel標準の `bigint unsigned` 相当ではなく、PostgreSQLの `bigserial` またはUUIDを業務要件に応じて選択する。
* 第0段階ではLaravel標準の `id` を基本とする。

### 3-3. 外部キー

* 外部キーは `{単数テーブル名}_id` とする。
* 例: `customer_id`, `product_id`, `employee_id`
* 外部キー制約は原則設定する。

### 3-4. 日時

* 作成日時、更新日時は `created_at`, `updated_at` とする。
* 業務日付は用途別に明示する。
* 例: `document_date`, `shipment_date`, `billing_date`, `closed_at`

## 4. 金額・数量・税額

1. 金額、数量、税額、酒税、単位換算値に `float` または `double` を使わない。
2. 固定小数精度の `numeric` を使う。
3. 推奨例は以下とする。
   * 数量: `numeric(18, 4)`
   * 単価: `numeric(18, 4)`
   * 金額: `numeric(18, 2)`
   * 税額: `numeric(18, 2)`
   * 酒税: `numeric(18, 2)`
   * 容量: `numeric(18, 4)`
   * Alc%: `numeric(5, 2)`
4. 丸め方式、端数処理単位、計算精度は確定時に保存する。

## 5. 確定済みデータ

確定済みデータには、確定時点の判断材料を保存する。

対象例は以下とする。

* 商品名
* 伝票表示名
* 単価
* 価格表ID
* 価格ルールID
* 税率
* 税額
* 酒税ルールID
* 酒類分類
* 容量
* Alc%
* 単位換算結果
* 丸め方式
* ロット引当情報

確定済みデータの表示、再発行、税務集計は、原則として確定時保存値を使用する。

## 6. 履歴と現在値

履歴台帳と現在値は分離する。

例は以下とする。

| 領域 | 正本 | 現在値 |
| --- | --- | --- |
| 在庫 | `stock_movements` | 初期実装では集計Service。月次確定値は `stock_monthly_balances` |
| 売掛 | `invoice_headers`, `payment_schedules`, `payments`, `payment_allocations` | 初期実装では集計Service。月次確定値は `receivable_monthly_balances` |
| 酒税 | 確定済み出荷明細の酒税保存値 | `liquor_tax_monthly_filings`, `liquor_tax_monthly_filing_lines` |
| 消費税 | 確定済み請求明細の消費税保存値 | `consumption_tax_monthly_filings`, `consumption_tax_monthly_filing_lines` |
| 帳票 | 確定済み業務データ | `report_exports` |
| 製造ロット | `production_lots` | ロット別在庫は `stock_movements`、引当は `shipment_lot_allocations`、予約は `shipment_stock_reservations` から算出 |

現在値だけを直接修正してはならない。差異は履歴台帳に調整レコードとして記録する。

`payments.amount` は実際の受領額、`payment_allocations.allocated_amount` は請求へ消込済みの額、`payments.unapplied_amount` はまだ用途を確定していない受領額とする。`unapplied_amount > 0` の入金は `status = review_required` とし、返金・繰越・別請求への充当を行うまで売掛残高の計算へ含めない。

第5段階時点では、売掛の月次繰越残高は `receivable_monthly_balances.outstanding_amount` を締め済み期末残高として保存し、翌月以降の繰越根拠とする。独立した `receivable_balances` または `monthly_closing_balances` テーブルはまだ作成しない。

第6段階では、製造ロットを `production_lots` で正規管理する。`stock_movements.production_lot_id` は製造ロット参照、`stock_movements.lot_code` は旧データ互換または補助表示用とする。

販売商品と製造ロットの候補関連は `product_production_lot` で管理する。`production_lots.product_id` は主関連または由来商品の補助参照であり、多対多の候補関連の正本は `product_production_lot` とする。

ロット別在庫は第6段階時点では専用残高テーブルを持たず、`stock_movements` から集計する。将来、参照性能が必要になった場合はロット別現在値テーブルを追加できるが、その場合も正本は在庫移動履歴とする。

## 7. 状態管理

状態を持つテーブルには `status` を持たせる。

ただし、`status` は直接更新してはならない。状態変更はService層の状態遷移処理を通す。

状態遷移の仕様は `docs/state-machine.md` に従う。

## 8. 論理削除・無効化

1. 履歴に参照されるマスタは物理削除しない。
2. ユーザー、従業員、取引先、商品は原則として無効化で扱う。
3. 無効化には `is_active` または `disabled_at` を使う。
4. 物理削除は、履歴に参照されず、業務上の事実を壊さない一時データに限定する。

## 9. 監査ログ

重要操作は `audit_logs` に記録する。

最低限の記録項目は以下とする。

* 操作日時
* 操作ユーザー
* 対象テーブル
* 対象ID
* 操作種別
* 変更前
* 変更後
* 操作理由
* 承認者
* IPアドレス
* user agent
* request_id

監査ログは原則削除不可とする。

## 10. migration 方針

1. migrationは小さく分ける。
2. 1つのmigrationに無関係な複数領域を混在させない。
3. 外部キー、インデックス、一意制約を明示する。
4. 確定済みデータや締め済みデータに影響する変更は、必ず移行方針と検証方針を用意する。
5. 本番データを壊す可能性があるmigrationは禁止する。

## 11. VIEW・集計テーブル

1. 一覧、検索、帳票では必要に応じて `VIEW` を使う。
2. 複雑JOINを画面や帳票ごとに乱立させない。
3. 集計テーブルは正本ではなく、再生成可能な派生値として扱う。
4. 集計テーブルを使う場合は、更新タイミングと正本を明確にする。

## 12. 禁止事項

1. 過去伝票を直接更新しない。
2. 締め済みデータを直接更新しない。
3. 現在庫残高だけを直接修正しない。
4. 金額、数量、税額、酒税に浮動小数を使わない。
5. 税率や酒税をコード固定値にしない。
6. マスタ変更で過去履歴の意味が変わる設計にしない。
## 6-x 出荷ロット引当

出荷ロット引当は `shipment_lot_allocations` で管理する。引当前の在庫正本は引き続き `stock_movements` であり、`shipment_lot_allocations.status = allocated` の数量はロット別現在庫および商品別現在庫の `allocated_quantity` として利用可能在庫から控除する。出荷確定後は引当を `confirmed` にし、ロット付きの出荷 `stock_movements` を作成する。確定済み引当は在庫を二重控除しない。

## 6-y 在庫予約

出荷前の在庫予約は `shipment_stock_reservations` で管理する。`status = reserved` の数量は商品別現在庫の `reserved_quantity` として利用可能在庫から控除する。同一出荷行でロット引当がある場合、同一商品、在庫場所、単位の引当数量を予約残から差し引き、予約と引当を二重控除しない。出荷確定後は予約を `confirmed` にし、在庫減少は `stock_movements` を正本とする。

## 6-z 受注

受注は `sales_orders` と `sales_order_lines` で管理する。受注は出荷伝票とは別の業務事実であり、受注作成だけでは在庫移動、請求、売掛を発生させない。`sales_order_lines.quantity` は受注数量、`remaining_quantity` は後続の出荷指示・分納・未出荷残管理に使う残数量とする。

## 6-za 商品SKU表示

販売商品は1SKUを `products` 1レコードとして扱う。容量違い、火入・生、色違い、形状違いは別SKUである。

商品入力画面では、同名商品の誤選択を避けるため、少なくとも以下を表示する。

* 銘柄: `brand_name`
* シリーズ: `series_name`
* 容量: `capacity_value` と `capacity_unit_id`
* スタイル名: `style_name`

`style_name` は商品種別を問わず使う。酒では火入・生など、食品では黒米・米こうじなど、酒粕では板粕・練り粕など、物販では色や仕上げを入れる。

## 6-aa 出荷指示

出荷指示は `shipment_instructions` と `shipment_instruction_lines` で管理する。出荷指示は受注明細の未出荷残を出荷作業へ回す指示であり、作成だけでは在庫移動、請求、売掛を発生させない。出荷指示作成時に `sales_order_lines.remaining_quantity` を減算し、受注ヘッダ状態を `received`、`partially_instructed`、`instructed` のいずれかへ更新する。

## 6-ab 品出

品出は `shipment_picks` と `shipment_pick_lines` で管理する。品出は出荷指示に対する作業実績であり、作成だけでは在庫移動、請求、売掛を発生させない。`shipment_instruction_lines.picked_quantity` は品出済み数量を表し、出荷指示明細数量を超えてはならない。出荷指示ヘッダ状態は `instructed`、`partially_picked`、`picked` のいずれかへ更新する。

## 7-1 品出から出荷伝票ドラフト

品出から作成された出荷伝票ドラフトは、`shipment_headers.source_shipment_pick_id` で元品出を参照する。出荷伝票明細は `shipment_lines.source_shipment_pick_line_id` で元品出明細を参照する。両方とも一意制約を持たせ、同じ品出または品出明細から重複して出荷伝票ドラフトを作成しない。

## 7-8 品出由来出荷伝票と在庫接続

品出由来の出荷伝票ドラフトも、通常の出荷伝票ドラフトと同じ `shipment_stock_reservations` と `shipment_lot_allocations` を使用する。出荷確定時は予約と引当を `confirmed` にし、在庫減少は `stock_movements` にロット付きで記録する。元品出・元品出明細の参照は、在庫予約、ロット引当、出荷確定後も保持する。

## 7-9 品出由来出荷伝票の取消

品出由来の出荷伝票を取り消す場合、`shipment_headers.status = cancelled` として出荷伝票側で取消を表現する。`shipment_picks`、`shipment_instructions`、`sales_orders` の数量状態は自動更新しない。取消後も `shipment_headers.source_shipment_pick_id` と `shipment_lines.source_shipment_pick_line_id` は保持し、元品出との接続履歴を削除しない。

## 7-3 から 7-6 取消接続

受注、出荷指示、品出の取消は既存テーブルの `status`、`cancelled_at`、`cancelled_reason` を使用する。取消ではレコードを削除しない。

品出取消では `shipment_picks.status = cancelled` とし、出荷指示明細の `picked_quantity` を戻す。出荷指示取消では `shipment_instructions.status = cancelled` とし、受注明細の `remaining_quantity` を戻す。受注取消では `sales_orders.status = cancelled` とし、受注を業務上無効にする。取消は品出、出荷指示、受注の逆順を基本とする。
