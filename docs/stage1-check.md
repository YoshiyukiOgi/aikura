# 第1段階 総合確認

## 確認日

2026-05-23

## 対象

第1段階で作成した以下の基盤を確認した。

* 取引先関連マスタ
* 商品・単位関連マスタ
* 価格表・価格ルール
* 出荷伝票draft作成
* draft明細への価格適用
* 出荷伝票confirmed確定
* 出荷伝票cancel

## 確認結果

### Docker

`docker compose ps` で以下の起動を確認した。

* `aikura_app`
* `aikura_nginx`
* `aikura_postgres`
* `aikura_redis`

PostgreSQLとRedisは `healthy` を確認した。

### HTTP疎通

`http://localhost:8080` が以下を返すことを確認した。

```json
{
  "name": "AI蔵",
  "status": "ok"
}
```

### migration

以下を含むmigrationが `Ran` であることを確認した。

* `2026_05_23_010001_create_customer_master_tables`
* `2026_05_23_020001_create_product_unit_master_tables`
* `2026_05_23_030001_create_price_tables`
* `2026_05_23_040001_create_shipment_draft_tables`
* `2026_05_23_050001_add_draft_pricing_to_shipment_lines`
* `2026_05_23_060001_add_confirmed_snapshot_to_shipment_lines`

### seed

`php artisan db:seed` を実行し、以下を確認した。

* roles: 1
* permissions: 11
* transaction_categories: 3
* settlement_receivable_categories: 3
* billing_cycles: 2
* units: 9
* price_lists: 4
* shipment_sequences: 1

### テスト

`php artisan test` の結果は以下。

```text
Tests: 60 passed (265 assertions)
```

## 完了判定

第1段階は、マスタと出荷伝票MVPとして以下を満たした。

* 取引先を登録できる土台がある。
* 商品と単位を登録できる土台がある。
* 価格をproductsに固定保持せず、価格表・価格ルールで解決できる。
* 出荷伝票をdraftで作成できる。
* draft明細へ価格を適用できる。
* confirmed時に商品名・単価・価格ルール・単位・容量・Alc%を固定保存できる。
* 商品マスタや価格ルール変更後も、confirmed済み伝票のスナップショットは変わらない。
* draftおよびconfirmedの出荷伝票を取消でき、取消理由と監査ログを残せる。

第1段階は完了とする。

