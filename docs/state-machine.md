## 第6段階追加: 受注・出荷指示・品出

受注は `sales_orders.status` で管理し、`received`、`partially_instructed`、`instructed` のいずれかを取る。出荷指示作成時に受注明細の `remaining_quantity` を減算し、全明細の残数量から状態を再計算する。

```text
received -> partially_instructed -> instructed
received -> instructed
```

出荷指示は `shipment_instructions.status` で管理し、作成時は `instructed` とする。品出作成時に出荷指示明細の `picked_quantity` を加算し、全明細の未品出数量から `instructed`、`partially_picked`、`picked` のいずれかへ再計算する。

```text
instructed -> partially_picked -> picked
instructed -> picked
```

品出は `shipment_picks.status = picked` として作業実績を記録する。品出作成だけでは在庫移動、請求、売掛を発生させない。

## 第7段階追加: 接続・取消

品出から出荷伝票ドラフトを作成した後は、出荷伝票側の状態遷移に従う。元品出は `shipment_headers.source_shipment_pick_id`、元品出明細は `shipment_lines.source_shipment_pick_line_id` で追跡する。

```text
shipment_pick:picked -> shipment:draft
shipment:draft -> confirmed -> billed
```

受注、出荷指示、品出の取消は、業務発生順の逆順を原則とする。品出済みの出荷指示は先に品出取消を行い、出荷指示済みの受注は先に出荷指示取消を行う。

```text
shipment_pick:picked -> shipment_pick:cancelled
shipment_instruction:instructed -> shipment_instruction:cancelled
sales_order:received -> sales_order:cancelled
```

出荷伝票ドラフトへ変換済みの品出は直接取り消さず、出荷伝票側の取消または訂正で扱う。

# 状態遷移設計

## 1. 位置づけ

この文書は、出荷、請求、入金、在庫、月次締めの状態遷移を定義する。

状態変更はService層経由のみ許可する。Controller、画面、Seeder、手動SQLから `status` を直接変更してはならない。

## 2. 共通原則

1. 状態は業務上の事実を表す。
2. 状態遷移は一方向を基本とする。
3. 取消、訂正、調整は元データの直接書換ではなく、関連レコードで表現する。
4. 状態遷移時には、権限、締め状態、対象データの整合性を確認する。
5. 重要な状態遷移は監査ログを必須とする。

## 3. 共通状態

代表的な状態は以下とする。

| status | 意味 |
| --- | --- |
| `draft` | 下書き。業務確定前。 |
| `confirmed` | 確定済み。計算結果やスナップショットが固定された状態。 |
| `allocated` | 在庫または入金などが割当済み。 |
| `review_required` | 入金は受領済みだが、未消込残高があり担当者確認が必要。 |
| `picked` | 品出済み。 |
| `shipped` | 出荷済み。 |
| `billed` | 請求済み。 |
| `closed` | 締め済み。 |
| `cancelled` | 取消済み。 |
| `corrected` | 訂正済み。 |

すべてのテーブルが全状態を使う必要はない。業務領域ごとに必要な状態だけを使う。

## 4. 出荷伝票

### 4-1. 基本遷移

```text
draft
↓ confirm
confirmed
↓ allocate
allocated
↓ pick
picked
↓ ship
shipped
↓ bill
billed
↓ close
closed
```

### 4-2. 取消・訂正

```text
draft -> cancelled
confirmed -> cancelled
confirmed -> corrected
shipped -> corrected
billed -> corrected
```

`closed` 後は直接訂正しない。翌月以降の調整伝票、赤伝、訂正伝票として処理する。

### 4-3. 編集可否

| status | 編集 |
| --- | --- |
| `draft` | 原則編集可 |
| `confirmed` | 主要項目編集不可 |
| `allocated` | ロット・数量の直接変更不可 |
| `picked` | 出荷作業に影響する変更不可 |
| `shipped` | 直接変更不可 |
| `billed` | 直接変更不可 |
| `closed` | 直接変更不可 |

## 5. 請求

### 5-1. 基本遷移

```text
draft
↓ confirm
confirmed
↓ issue
issued
↓ close
closed
```

### 5-2. 修正方針

請求確定後は直接更新しない。修正は調整、値引、訂正請求、再発行履歴として扱う。

請求書PDFは発行時点の固定帳票として保存する。

## 6. 入金・消込

### 6-1. 入金状態

```text
allocated
review_required
cancelled
```

### 6-2. 消込方針

入金と請求は多対多で消込可能とする。現行の登録処理は1回につき1つの入金予定への消込までであり、複数請求への一括配分は未実装である。

不足入金、過入金、振込手数料、値引、相殺、次月繰越を区別する。

過入金は、請求残額までを `payment_allocations` に記録し、超過額を `payments.unapplied_amount` に残す。超過額がある入金は `review_required` とする。`review_required` はエラーではなく、返金・次回請求充当・相殺等の判断待ちであり、勝手に売掛残高を減額してはならない。

締め済み入金の修正は直接更新ではなく、調整処理として扱う。

## 7. 在庫

### 7-1. 在庫移動状態

```text
draft
↓ confirm
confirmed
↓ close
closed
```

### 7-2. 基本方針

在庫の正本は `stock_movements` とする。

現在庫である `stock_balances` は参照性能向上のための現在値とする。

在庫調整、棚卸差異、破損、廃棄、訂正は在庫移動として記録する。

## 8. 月次締め

### 8-1. 基本遷移

```text
draft
↓ confirm
confirmed
↓ close
closed
```

月次残高や申告データは、ドラフト作成時点で計算済みの値を保存する。第5段階時点では `calculated` を永続状態としては使わず、`calculated_at` などの日時項目で計算実行時点を持つ。

対象例は以下とする。

* `stock_monthly_balances`
* `liquor_tax_monthly_filings`
* `consumption_tax_monthly_filings`
* `receivable_monthly_balances`

月次税務申告は `draft -> confirmed` を基本とし、締め済み期間制御では `confirmed` と `closed` を同等に閉じた期間として扱う。

売掛月次残高は `draft -> confirmed -> closed` とし、`confirmed` で残高を固定、`closed` で月次請求締め完了を表す。

### 8-2. 締解除

締解除は原則禁止とする。

許可する場合は以下を必須とする。

* 管理者権限
* 理由入力
* 承認
* 監査ログ
* 再締め履歴

## 9. 実装ルール

1. 状態変更は専用Serviceで行う。
2. 状態変更Serviceはトランザクション内で実行する。
3. 状態変更前に現在状態、権限、締め状態、関連データを検証する。
4. 状態変更時は必要なスナップショット、在庫移動、監査ログを同一トランザクションで作成する。
5. 不正な状態遷移は例外にする。

## 10. テスト必須項目

状態遷移について以下のFeature Testを必須とする。

* 許可された遷移が成功する。
* 禁止された遷移が失敗する。
* 権限不足で失敗する。
* 締め済みデータの直接変更が失敗する。
* 状態遷移時に監査ログが残る。
## 7-9 品出由来出荷伝票の取消

品出由来の出荷伝票を取り消しても、元品出、元出荷指示、元受注の数量状態は自動で戻さない。品出は作業実績として保持し、出荷伝票取消は出荷伝票側の状態遷移と在庫・税の取消で完結させる。取消後も `source_shipment_pick_id` と `source_shipment_pick_line_id` は保持する。
