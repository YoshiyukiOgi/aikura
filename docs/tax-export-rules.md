# 輸出・免税時の税判定ルール

## 目的

出荷伝票を作り直す、または税計算ロジックを再実装する場合に、海外出荷・免税取引が商品マスタ由来の課税判定に埋もれないようにする。

この文書は、消費税と酒税の判定優先順位、保存すべきスナップショット、未実装だった点を明確にする。

## 前提

出荷伝票ヘッダは `settlement_receivable_category_id` を持つ。

`settlement_receivable_categories` は以下を持つ。

| カラム | 意味 |
| --- | --- |
| `export_type` | 国内取引か輸出取引か |
| `consumption_tax_type` | 消費税上の扱い。例: `taxable`, `non_taxable`, `export_exempt` |
| `liquor_tax_type` | 酒税上の扱い。例: `taxable`, `exempt` |

商品は以下を持つ。

| 場所 | 意味 |
| --- | --- |
| `products.consumption_tax_category_id` | 商品ごとの標準消費税区分 |
| `sake_product_details.liquor_tax_category_code` | 酒類商品の標準酒税区分 |

## 消費税判定

出荷確定時の消費税区分は、商品だけで決めてはならない。

優先順位は以下とする。

1. 出荷ヘッダの `settlement_receivable_category.consumption_tax_type` が `export_exempt` の場合、消費税区分は `export_exempt` とする。
2. 出荷ヘッダの `settlement_receivable_category.consumption_tax_type` が `non_taxable` の場合、消費税区分は非課税相当の区分とする。
3. 上記以外の場合、商品マスタの `products.consumption_tax_category_id` を使う。
4. 商品マスタに消費税区分が未設定の場合、商品種別から標準税率・軽減税率を推定する。

出荷確定時には、解決後の消費税区分を `shipment_lines.confirmed_consumption_tax_*` に保存する。

輸出免税の場合は、以下となる。

| 項目 | 値 |
| --- | --- |
| `confirmed_consumption_tax_category_code` | `export_exempt` |
| `confirmed_consumption_taxability` | `exempt` |
| `confirmed_consumption_tax_rate_id` | `null` |
| `confirmed_consumption_tax_rate` | `null` |
| 表示上の消費税額 | `0.00` |

## 酒税判定

出荷確定時の酒税区分も、商品だけで決めてはならない。

優先順位は以下とする。

1. 出荷ヘッダの `settlement_receivable_category.liquor_tax_type` が `exempt` の場合、酒税区分は `export_exempt_liquor` とする。
2. 上記以外の場合、酒類商品マスタの `sake_product_details.liquor_tax_category_code` を使う。
3. 酒類商品マスタに酒税区分が未設定の場合、酒類なら `seishu`、非酒類なら `non_liquor` を使う。

出荷確定時には、解決後の酒税区分と計算根拠を `shipment_lines.confirmed_liquor_tax_*` に保存する。

輸出免税の場合は、以下となる。

| 項目 | 値 |
| --- | --- |
| `confirmed_liquor_tax_category_code` | `export_exempt_liquor` |
| `confirmed_liquor_taxability` | `exempt` |
| `confirmed_liquor_tax_rule_id` | `null` |
| `confirmed_liquor_taxable_kl` | `0.000000` |
| `confirmed_liquor_tax_per_kl` | `null` |
| `confirmed_liquor_tax_estimated_amount` | `0.00` |

## スナップショット方針

半年後、またはマスタ変更後に出荷伝票を参照・再発行する場合は、現在のマスタを再解決してはならない。

確定済み出荷伝票では、以下を参照する。

| 用途 | 参照先 |
| --- | --- |
| 商品名 | `confirmed_product_name`, `confirmed_display_name` |
| 数量・単位 | `confirmed_quantity`, `confirmed_unit_name` |
| 単価 | `confirmed_unit_price` |
| 消費税区分・税率 | `confirmed_consumption_tax_*` |
| 酒税区分・見込額 | `confirmed_liquor_tax_*` |

## 未実装だった点

現状の出荷確定処理では、以下が不足していた。

1. 消費税区分の解決で `settlement_receivable_categories.consumption_tax_type` を見ていない。
2. 酒税区分の解決で `settlement_receivable_categories.liquor_tax_type` を見ていない。
3. `export_exempt` と `export_exempt_liquor` のマスタは存在するが、出荷確定時の優先判定に組み込まれていない。
4. 出荷伝票発行時の消費税額は保存値ではなく、`confirmed_quantity`, `confirmed_unit_price`, `confirmed_consumption_tax_rate`, `confirmed_consumption_taxability` から計算している。

## 実装時の受け入れ条件

再実装時は、最低限以下をテストする。

1. 国内出荷の酒類商品は、商品マスタ由来の標準消費税・酒税区分になる。
2. 輸出出荷の酒類商品は、商品マスタが課税でも消費税 `export_exempt`、酒税 `export_exempt_liquor` になる。
3. 輸出出荷の消費税額表示は `0.00` になる。
4. 輸出出荷の酒税見込額は `0.00` になる。
5. 出荷確定後に商品マスタ、税率マスタ、酒税マスタを変更しても、確定済み出荷伝票の表示と帳票出力は `confirmed_*` の保存値を使う。
6. 請求作成時は、出荷明細の `confirmed_consumption_tax_*` を請求明細へ転記する。

## 実装先の目安

消費税は、商品だけを受け取る resolver ではなく、出荷ヘッダまたは決算売掛区分も受け取れる resolver にする。

例:

```text
ResolveShipmentConsumptionTaxCategoryService
  input: ShipmentHeader, Product
  output: ConsumptionTaxCategory
```

酒税も同様に、商品だけを受け取る計算サービスではなく、出荷ヘッダまたは決算売掛区分を考慮する。

例:

```text
CalculateShipmentLiquorTaxService
  input: ShipmentHeader, Product, quantity, liquor_tax_transfer_date
  output: CalculatedLiquorTax
```

Controllerや画面に税判定を書いてはならない。税判定はService層に置く。
