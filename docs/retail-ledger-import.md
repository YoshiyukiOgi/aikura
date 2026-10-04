# 玉川Access検証データの小売元帳取込

`/retail/ledger` の「玉川Access検証データを取込」から、月ごとのJSONを古い月から順に登録する。
同一内容のファイルは内容ハッシュで判定され、再取込しても伝票を二重に作成しない。

```json
{
  "period_from": "2026-07-01",
  "period_to": "2026-07-31",
  "documents": [
    {
      "source_document_id": "access-header-10001",
      "document_no": "10001",
      "business_date": "2026-07-03",
      "retail_customer_id": 123,
      "lines": [
        {
          "source_line_id": "access-detail-501",
          "source_product_id": 45,
          "description": "清酒 一升瓶",
          "quantity": 2,
          "unit_price": 2500,
          "amount": 5000
        },
        {
          "source_line_id": "access-detail-502",
          "source_product_id": -1,
          "description": "入金",
          "amount": -3000
        }
      ]
    }
  ]
}
```

- `source_product_id: -1` は必ず入金明細として保存される。物理的な商品、在庫移動、酒蔵出荷・発注には変換しない。
- 金額はAccess原本の符号をそのまま保存する。過去データに正符号の入金がある場合も、取込時に絶対値化しない。
- `retail_customer_id` は小売側の取引先IDである。Access側IDとの対応表は取込前に確定する。
- 月次締め後は当月の取込・登録を受け付けない。訂正は翌月の調整明細として扱う。
- 取込後、月次締め前にAccessの残高一覧表と顧客別の売上・入金・残高を照合する。
