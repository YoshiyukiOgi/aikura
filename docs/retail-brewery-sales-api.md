# 小売販売連動用API仕様

## 基本方針

- 小売販売伝票1件を、蔵側受注1件として扱う。
- 小売側は蔵側の受注IDと受注番号を保存し、以後の変更・取消では必ず受注IDを指定する。
- 出荷指示前は元受注を変更または取消する。
- 出荷処理開始後は元受注を変更せず、差分を `retail_sale_correction` として登録する。
- 蔵側で請求確定済みの場合は変更・取消・赤伝依頼を拒否する。
- `source_type` と `source_reference` の組み合わせを再送時の識別子にする。

## 販売確定

`POST /api/v1/sales-orders`

主な項目:

```json
{
  "customer_id": 123,
  "order_date": "2026-08-02",
  "customer_order_number": "RS-20260802-0001",
  "source_type": "retail_sale",
  "source_reference": "RS-20260802-0001",
  "auto_release_to_shipping": false,
  "lines": [
    {"product_id": 10, "quantity": "2.0000", "unit_id": 1}
  ]
}
```

小売側の保存項目は `brewery_sales_order_id`、`brewery_order_number`、`brewery_sync_status`、`brewery_synced_at`。

## 出荷指示前の変更

`PUT /api/v1/sales-orders/{brewery_sales_order_id}`

全明細を送信する。蔵側受注明細IDが分かる行は `lines[].id` に指定する。出荷指示済み数量を下回る変更は拒否され、小売側は差分訂正へ切り替える。

## 出荷指示前の取消

`POST /api/v1/sales-orders/{brewery_sales_order_id}/cancel`

```json
{"reason": "小売販売取消 RS-20260802-0001"}
```

出荷指示済みの場合は拒否され、小売側は差分訂正へ切り替える。

## 出荷処理後の訂正・赤伝依頼

`POST /api/v1/sales-orders`

- 変更差分: `source_type=retail_sale_correction`
- 赤伝: `source_type=retail_sale_credit_note`
- `source_reference` は小売伝票番号に操作種別を加え、一意にする。
- 数量は増加分を正数、取消分を負数で送る。
- 蔵側の請求確定済み判定に該当した場合は送信しない。

蔵側は上記2種類に限り負数明細を受け付ける。通常受注 `retail_sale` では数量0以下を拒否する。

## 状態

| 状態 | 意味 |
|---|---|
| `not_required` | 外部仕入商品のみで蔵連携対象なし |
| `ordered` | 蔵受注作成済み |
| `order_revised` | 出荷指示前の元受注を変更済み |
| `order_cancelled` | 出荷指示前の元受注を取消済み |
| `correction_sent` | 出荷処理後の差分訂正または赤伝依頼済み |
| `revision_no_change` | 蔵商品について数量差分なし |
| `failed` | 蔵連携失敗。エラー内容を保存 |
