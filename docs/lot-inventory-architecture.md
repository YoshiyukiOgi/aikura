# ロット中心在庫設計

## 原則

商品は在庫の属性ではない。商品は販売・出荷時にロットへ与えられる販売上の意味である。

在庫は `production_lot_id + stock_location_id + unit_id` で識別・集計する。商品別在庫、商品別在庫予約、商品とロットの恒久的な候補リンクは保持しない。

## データの責務

- `production_lots`: ロット固有の製造情報、包装単位、容量、分析値を保持する。
- `stock_movements`: ロット、在庫場所、単位、数量の増減を保持する。`product_id` は持たない。
- `stock_lot_monthly_balances`: ロット、在庫場所、単位ごとの月末残高を保持する。
- `non_sales_stock_operation_lines`: 販売外の在庫増減対象ロットを保持する。`production_lot_id` は必須で、`product_id` は持たない。
- `shipment_lot_allocations`: 出荷明細の商品と、実際に選んだロットの取引時点の関係を保持する。

## 出荷候補

ピッキング時に、出荷商品の規格と現在のロット在庫から候補を動的に作る。候補判定には次を使用する。

- 在庫場所
- 利用可能数量
- 在庫単位
- 容量と容量単位
- 分析確定状態
- アルコール度数と許容範囲
- ロットの有効状態

単位・容量が不一致のロットと分析未確定ロットは選択不可とする。アルコール度数が許容範囲外のロットは候補から除外せず、理由入力と承認を必要とする候補として表示する。

候補数量は商品在庫ではない。同じロットが複数商品の候補になり得るため、商品ごとの候補数量を全体在庫として合算してはならない。

## 廃止対象

- `production_lots.product_id`
- `product_production_lot`
- `stock_movements.product_id`
- `non_sales_stock_operation_lines.product_id`
- 販売外出入の消費税・酒税区分と酒税計算スナップショット
- `inventory_count_lines.product_id`
- `stock_lot_monthly_balances.product_id`
- `stock_monthly_balances`
- `shipment_stock_reservations`
- 商品別在庫予約API

出荷商品の履歴は出荷明細に、商品とロットの実績関係は `shipment_lot_allocations` に残す。
