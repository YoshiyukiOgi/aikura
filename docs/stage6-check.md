# 第6段階 進行確認

## 確認日

2026-05-24

## 6-1 製造ロット基礎

製造ロットを `production_lots` として正規管理する基礎を追加した。

実装済み:

* `production_lots` テーブル
* `ProductionLot` モデル
* `products` から製造ロットへの関連
* `stock_locations` から製造ロットへの関連
* `stock_movements.production_lot_id` による在庫移動から製造ロットへの参照

方針:

* `stock_movements.lot_code` は旧データ互換または補助表示用として残す。
* ロット管理の正本は `production_lots` とする。
* 販売商品と製造ロットの多対多関連、ロット別在庫残高、ロット引当は6-2以降で実装済み。

## 6-2 販売商品と製造ロットの関連

販売商品と製造ロットを多対多で関連付ける基礎を追加した。

実装済み:

* `product_production_lot` 中間テーブル
* `Product::linkedProductionLots()`
* `ProductionLot::linkedProducts()`
* 用途区分、優先順位、適用期間、既定フラグ、有効状態、理由、備考の保持
* 同一商品、同一ロット、同一用途区分の重複禁止

方針:

* `production_lots.product_id` は主関連または由来商品の補助参照として残す。
* 出荷候補、ブレンド候補、代替候補などの用途差は `usage_type` で区別する。
* ロット別在庫残高は6-3、実際の出荷引当は6-4で実装済み。

## 6-3 ロット別在庫

`stock_movements.production_lot_id` を基に、製造ロット別の現在庫を集計する基礎を追加した。

実装済み:

* `LotStockBalance`
* `LotStockBalanceService`
* 製造ロット、販売商品、在庫場所、単位別の現在庫集計
* `confirmed` と `closed` の在庫移動のみ集計
* `draft`、`cancelled`、取消済み、製造ロット未設定の在庫移動を除外

方針:

* 6-4以降、引当数量はロット引当から算出する。
* 6-5以降、予約数量は在庫予約から算出し、同一出荷行のロット引当分は予約残から控除する。
## 6-4 ロット引当

出荷行に対して、どの製造ロットから何本を引き当てたかを `shipment_lot_allocations` に記録する基礎を追加した。

実装済み:

* `shipment_lot_allocations` テーブル
* `ShipmentLotAllocation` モデル
* `ShipmentLine::lotAllocations()`
* `ProductionLot::shipmentLotAllocations()`
* `AllocateShipmentLineLotService`
* ロット別現在庫と商品別現在庫への引当数量反映
* 出荷確定時のロット別 `stock_movements` 作成
* 出荷取消時の戻し在庫移動への `production_lot_id` と `lot_code` 引き継ぎ

方針:

* ロット引当は出荷伝票が `draft` の間だけ行う。
* 引当数量は0超でなければならない。
* 製造ロットは有効かつ `active` のものだけを対象にする。
* 出荷商品と製造ロットの関係は、同一商品ロット、または有効な `product_production_lot` の `shipment_candidate` 関係で判定する。
* 引当中の数量は `allocated_quantity` として利用可能在庫から控除する。
* 出荷確定時、引当が存在する在庫管理対象明細は、明細数量と引当合計が一致しなければ確定できない。
* 出荷確定後、引当は `confirmed` になり、在庫減少はロット付きの `stock_movements` が正本になる。確定済み引当を二重に利用可能在庫から控除してはならない。
## 6-5 在庫予約

ロットを確定する前に、出荷行に対して商品、在庫場所、単位単位で在庫を予約する基礎を追加した。

実装済み:

* `shipment_stock_reservations` テーブル
* `ShipmentStockReservation` モデル
* `ShipmentLine::stockReservations()`
* `ReserveShipmentLineStockService`
* 商品別現在庫への予約数量反映
* ロット引当済み数量を同一出荷行の予約残から控除する二重控除防止
* 出荷確定時の予約 `confirmed` 化

方針:

* 在庫予約は出荷伝票が `draft` の間だけ行う。
* 予約数量は0超でなければならない。
* 在庫管理対象外の商品は予約できない。
* 無効、または在庫管理対象外の在庫場所には予約できない。
* `reserved` の予約数量は `reserved_quantity` として利用可能在庫から控除する。
* 同じ出荷行でロット引当が存在する場合、引当済み数量は予約残から差し引き、予約と引当を二重に控除してはならない。
* 出荷確定後、予約は `confirmed` にし、在庫減少は `stock_movements` が正本になる。
## 6-6 受注

顧客から注文を受けた業務事実を、出荷伝票とは分けて `sales_orders` と `sales_order_lines` に記録する基礎を追加した。

実装済み:

* `sales_orders` テーブル
* `sales_order_lines` テーブル
* `SalesOrder` モデル
* `SalesOrderLine` モデル
* `CreateSalesOrderService`
* `sales_order` 採番
* 受注作成時の監査ログ

方針:

* 受注は出荷伝票そのものではなく、出荷指示や出荷伝票の前段となる注文事実として扱う。
* 受注作成時点では在庫移動、請求、売掛を発生させない。
* 受注番号は `sales_order` 採番を使う。
* 受注作成時は顧客、商品、単位の有効性を検証する。
* 受注明細には `quantity` と `remaining_quantity` を持たせ、後続の出荷指示・分納・未出荷残管理へ接続できるようにする。
* 受注作成時は監査ログを保存する。
## 6-7 出荷指示

受注明細の未出荷残から、倉庫・蔵へ出荷作業を依頼する `shipment_instructions` と `shipment_instruction_lines` の基礎を追加した。

実装済み:

* `shipment_instructions` テーブル
* `shipment_instruction_lines` テーブル
* `ShipmentInstruction` モデル
* `ShipmentInstructionLine` モデル
* `CreateShipmentInstructionService`
* `shipment_instruction` 採番
* 出荷指示作成時の監査ログ
* 受注明細 `remaining_quantity` の減算
* 受注ヘッダ状態の `received` / `partially_instructed` / `instructed` 更新

方針:

* 出荷指示は受注と出荷伝票の間に置く作業指示であり、作成時点では在庫移動、請求、売掛を発生させない。
* 出荷指示数量は受注明細の未出荷残を超えてはならない。
* 同一出荷指示内で同じ受注明細を重複指定してはならない。
* 複数受注明細をまとめる場合、同一顧客の受注明細だけを対象にする。
* 出荷指示作成後、受注明細の `remaining_quantity` を減算し、受注ヘッダ状態を再計算する。
* 出荷指示作成時は監査ログを保存する。
## 6-8 品出

出荷指示に対して、実際に品出した数量を `shipment_picks` と `shipment_pick_lines` に記録する基礎を追加した。

実装済み:

* `shipment_instruction_lines.picked_quantity`
* `shipment_picks` テーブル
* `shipment_pick_lines` テーブル
* `ShipmentPick` モデル
* `ShipmentPickLine` モデル
* `PickShipmentInstructionService`
* `shipment_pick` 採番
* 品出作成時の監査ログ
* 出荷指示状態の `instructed` / `partially_picked` / `picked` 更新

方針:

* 品出は出荷指示に対する作業実績であり、作成時点では在庫移動、請求、売掛を発生させない。
* 品出数量は出荷指示明細の未品出数量を超えてはならない。
* 同一品出内で同じ出荷指示明細を重複指定してはならない。
* 品出は分割して実行できる。
* 品出作成後、出荷指示明細の `picked_quantity` を加算し、出荷指示ヘッダ状態を再計算する。
* 品出作成時は監査ログを保存する。
