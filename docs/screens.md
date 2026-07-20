# 画面方針

## 1. 位置づけ

画面はDB、Service、状態遷移、月次締め制御に従属する。

この文書は本番業務画面の設計方針を定める。画面の実装状況は `docs/current-state.md` を参照する。

## 2. 基本方針

1. 画面側で税額、酒税、売掛残高、在庫残高を確定計算しない。
2. 入力補助や検索補助は画面側で行ってよい。
3. 確定済み、締め済み、取消済みデータは状態に応じて編集不可にする。
4. 重要操作では理由入力を求める。
5. 監査ログ対象操作はService経由で実行する。

## 3. 画面化の対象

* 酒税申告確認
* 消費税申告確認
* 売掛月次残高確認
* 月次締め確認
* 帳票出力履歴確認

以下の画面も業務上必要である。

* 返品・戻入登録: 元出荷・元請求を指定し、部分返品、元税率、在庫戻し、酒税戻入を記録する。
* 要確認入金一覧: `payments.status = review_required`、未消込残高、振込元情報、判断・処理履歴を確認する。
* 入金再配分・返金・繰越処理: 未消込残高をどの請求へ充当するか、または返金・相殺するかを理由付きで確定する。

返品・戻入と入金再配分は現時点では未実装であり、画面だけを先行実装して金額や税額を確定してはならない。

## 4. 第8段階 API/画面入口

第8段階では、画面の前段としてAPI入口を整備する。API ControllerはServiceを呼び出す薄い層とし、税額、酒税、価格、在庫、売掛、月次残高の確定計算を行ってはならない。

初期API入口は `/api/v1` を基準とする。業務別APIは受注、出荷指示、品出、出荷伝票、在庫、請求、入金、税務、月次締めに分ける。

保護APIは認証と権限middlewareを通す。画面は、権限不足または状態不整合による拒否を業務エラーとして表示し、DB例外や内部例外を直接表示してはならない。

## 5. 受注API/画面基礎

受注画面は受注APIを通じて、一覧、詳細、作成、取消を行う。受注番号採番、状態更新、監査ログはService層で行い、画面またはControllerで直接処理しない。

商品選択では、商品名だけを表示しない。容量違い、スタイル違いの誤選択を避けるため、銘柄、シリーズ、容量、スタイル名を同時に表示する。

受注APIの初期対象は以下とする。

* `GET /api/v1/sales-orders`
* `GET /api/v1/sales-orders/{sales_order}`
* `POST /api/v1/sales-orders`
* `POST /api/v1/sales-orders/{sales_order}/price`
* `PATCH /api/v1/sales-orders/{sales_order}/lines/{sales_order_line}/price`
* `POST /api/v1/sales-orders/{sales_order}/lines/{sales_order_line}/reprice`
* `POST /api/v1/sales-orders/{sales_order}/cancel`

受注一覧APIは `q`、`customer_id`、`status`、`order_date_from`、`order_date_to`、`page`、`per_page` を受け付ける。応答には `pagination.current_page`、`last_page`、`per_page`、`total` を含める。

## 6. 出荷指示API/画面基礎

出荷指示画面は出荷指示APIを通じて、一覧、詳細、作成、取消を行う。出荷指示番号採番、受注残更新、状態更新、監査ログはService層で行い、画面またはControllerで直接処理しない。

出荷指示APIの初期対象は以下とする。

* `GET /api/v1/shipment-instructions`
* `GET /api/v1/shipment-instructions/{shipment_instruction}`
* `POST /api/v1/shipment-instructions`
* `POST /api/v1/shipment-instructions/{shipment_instruction}/cancel`

## 7. 品出API/画面基礎

品出画面は品出APIを通じて、一覧、詳細、作成、取消を行う。品出番号採番、出荷指示明細の品出済数量更新、出荷指示状態更新、監査ログはService層で行い、画面またはControllerで直接処理しない。品出作成時点では在庫移動、請求、売掛を発生させない。

品出APIの初期対象は以下とする。

* `GET /api/v1/shipment-picks`
* `GET /api/v1/shipment-picks/{shipment_pick}`
* `POST /api/v1/shipment-picks`
* `POST /api/v1/shipment-picks/{shipment_pick}/cancel`

## 8. 出荷伝票API/画面基礎

出荷伝票画面は出荷伝票APIを通じて、一覧、詳細、ドラフト作成、価格適用、確定、取消を行う。出荷伝票番号採番、価格解決、税スナップショット、酒税見込額、在庫移動、取消時の在庫戻し、監査ログはService層で行い、画面またはControllerで直接処理しない。販売後の返品・酒税戻入は取消画面と分ける。

出荷伝票APIの初期対象は以下とする。

* `GET /api/v1/shipments`
* `GET /api/v1/shipments/{shipment}`
* `POST /api/v1/shipments`
* `POST /api/v1/shipments/{shipment}/price`
* `POST /api/v1/shipments/{shipment}/confirm`
* `POST /api/v1/shipments/{shipment}/cancel`

## 9. 在庫API/画面基礎

在庫画面は在庫APIを通じて、現在庫、ロット在庫、出荷明細への在庫予約、出荷明細へのロット引当を行う。現在庫、予約可能数量、ロット引当可能数量はService層で算出し、画面またはControllerで直接計算しない。在庫予約とロット引当は出荷伝票ドラフトに対する保護であり、在庫移動は出荷確定時に発生させる。

在庫APIの初期対象は以下とする。

* `GET /api/v1/inventory/stock`
* `GET /api/v1/inventory/lot-stock`
* `POST /api/v1/inventory/reserve`
* `POST /api/v1/inventory/allocate`

## 10. 請求・入金API/画面基礎

請求・入金画面は請求・入金APIを通じて、請求一覧、請求詳細、請求ドラフト作成、請求確定、請求取消、入金予定一覧、入金予定作成、入金一覧、入金登録、入金取消、売掛残高照会を行う。請求金額、消費税、入金予定額、入金消込、売掛残高はService層で算出し、画面またはControllerで直接計算しない。過入金はエラー表示で終わらせず、未消込残高と `review_required` を明示する。未消込残高の再配分は、専用処理が実装されるまで操作不可とする。

請求・入金APIの初期対象は以下とする。

* `GET /api/v1/billing/invoices`
* `GET /api/v1/billing/invoices/{invoice}`
* `POST /api/v1/billing/invoices`
* `POST /api/v1/billing/invoices/{invoice}/confirm`
* `POST /api/v1/billing/invoices/{invoice}/cancel`
* `GET /api/v1/billing/payment-schedules`
* `POST /api/v1/billing/payment-schedules`
* `GET /api/v1/billing/payments`
* `POST /api/v1/billing/payments`
* `POST /api/v1/billing/payments/{payment}/cancel`
* `GET /api/v1/billing/receivables`

## 11. 税務API/画面基礎

税務画面は税務APIを通じて、酒税月次申告一覧、酒税月次申告詳細、酒税月次申告ドラフト作成、酒税月次申告確定、消費税月次申告一覧、消費税月次申告詳細、消費税月次申告ドラフト作成、消費税月次申告確定を行う。酒税移出集計、酒税額、消費税集計、消費税額、確定額はService層で算出し、画面またはControllerで直接計算しない。

税務APIの初期対象は以下とする。

* `GET /api/v1/tax/liquor-monthly-filings`
* `GET /api/v1/tax/liquor-monthly-filings/{filing}`
* `POST /api/v1/tax/liquor-monthly-filings`
* `POST /api/v1/tax/liquor-monthly-filings/{filing}/confirm`
* `GET /api/v1/tax/consumption-monthly-filings`
* `GET /api/v1/tax/consumption-monthly-filings/{filing}`
* `POST /api/v1/tax/consumption-monthly-filings`
* `POST /api/v1/tax/consumption-monthly-filings/{filing}/confirm`

## 12. 月次締めAPI/画面基礎

月次締め画面は月次締めAPIを通じて、在庫月次残高一覧、在庫月次残高ドラフト作成、在庫月次残高確定、売掛月次残高一覧、売掛月次残高ドラフト作成、売掛月次残高確定、売掛月次残高締めを行う。在庫数量、入庫数量、出庫数量、調整数量、売掛予定額、入金額、未回収額はService層で算出し、画面またはControllerで直接計算しない。

月次締めAPIの初期対象は以下とする。

* `GET /api/v1/monthly-closing/stock-balances`
* `POST /api/v1/monthly-closing/stock-balances`
* `POST /api/v1/monthly-closing/stock-balances/{year}/{month}/confirm`
* `GET /api/v1/monthly-closing/receivable-balances`
* `POST /api/v1/monthly-closing/receivable-balances`
* `POST /api/v1/monthly-closing/receivable-balances/{year}/{month}/confirm`
* `POST /api/v1/monthly-closing/receivable-balances/{year}/{month}/close`

## 13. 入力制御・状態別操作制御

画面はAPIの入力検証、権限拒否、業務状態拒否を区別して扱う。入力不備は422、未ログインは401、権限不足または無効ユーザーは403、状態不整合や締め済み期間への更新などの業務拒否は409として扱う。画面またはControllerで状態遷移可否を再実装せず、Service層の業務例外をAPI共通JSONエラーとして返す。

業務拒否時のAPI応答は以下を基準とする。

* HTTP status: `409`
* `error.code`: `business_rule_violation`
* `error.message`: Service層が返した業務エラーメッセージ
