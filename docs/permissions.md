# 権限方針

## 1. 基本方針

1. `roles`、`permissions`、中間テーブルで権限を管理する。
2. 重要操作は専用権限を要求する。
3. ユーザー削除ではなく無効化を基本とする。

月次締めでは以下の権限を使用する。

* `monthly_closing.view`: 月次締め状況閲覧
* `monthly_closing.execute`: 月次締めドラフト作成、確定、締め

## 2. 重要操作

* 価格変更
* 税ルール変更
* 酒税ルール変更
* 月次締め
* 締解除
* 在庫調整
* 伝票取消
* 入金消込
* 帳票再発行
* 監査ログ閲覧

## 3. 第8段階 API権限制御

第8段階では、API入口に権限制御を接続する。8-1ではAPI基礎だけを作り、実際の権限チェック接続は8-2で行う。

Controllerは権限判断を直接実装せず、認証ユーザーと権限コードをAuthorizationServiceまたは専用Middlewareへ渡す。

8-2時点では、APIの権限チェックは `permission:{permission_code}` middlewareで行う。middlewareは認証ユーザーを確認し、AuthorizationServiceへ権限判断を委譲する。未ログインは401、権限不足または無効ユーザーは403とする。

## 4. 受注API権限

受注APIでは以下の権限を使用する。

* `sales_order.view`: 受注一覧、受注詳細
* `sales_order.create`: 受注作成
* `sales_order.cancel`: 受注取消
* `price.change`: 受注価格の適用、明細単価の上書き、適用価格への復帰

## 5. 出荷指示API権限

出荷指示APIでは以下の権限を使用する。

* `shipment_instruction.view`: 出荷指示一覧、出荷指示詳細
* `shipment_instruction.create`: 出荷指示作成
* `shipment_instruction.cancel`: 出荷指示取消

## 6. 品出API権限

品出APIでは以下の権限を使用する。

* `shipment_pick.view`: 品出一覧、品出詳細
* `shipment_pick.create`: 品出作成
* `shipment_pick.cancel`: 品出取消

## 7. 出荷伝票API権限

出荷伝票APIでは以下の権限を使用する。

* `shipment.view`: 出荷伝票一覧、出荷伝票詳細
* `shipment.create`: 出荷伝票ドラフト作成
* `shipment.price`: 出荷伝票価格適用
* `shipment.confirm`: 出荷伝票確定
* `shipment.cancel`: 出荷伝票取消

## 8. 在庫API権限

在庫APIでは以下の権限を使用する。

* `inventory.view`: 現在庫、ロット在庫閲覧
* `inventory.reserve`: 出荷明細への在庫予約
* `inventory.allocate`: 出荷明細へのロット引当

## 9. 請求・入金API権限

請求・入金APIでは以下の権限を使用する。

* `billing.view`: 請求、入金予定、入金、売掛残高閲覧
* `billing.invoice.create`: 請求ドラフト作成
* `billing.invoice.confirm`: 請求確定
* `billing.invoice.cancel`: 請求取消
* `billing.payment_schedule.create`: 入金予定作成
* `billing.payment.create`: 入金登録
* `billing.payment.cancel`: 入金取消

## 10. 税務API権限

税務APIでは以下の権限を使用する。

* `tax.view`: 酒税月次申告、消費税月次申告閲覧
* `tax.liquor_filing.create`: 酒税月次申告ドラフト作成
* `tax.liquor_filing.confirm`: 酒税月次申告確定
* `tax.consumption_filing.create`: 消費税月次申告ドラフト作成
* `tax.consumption_filing.confirm`: 消費税月次申告確定

## 11. 承認API権限

承認APIでは以下の権限を使用する。

* `approval.view`: 承認依頼一覧、承認依頼詳細
* `approval.request`: 承認依頼作成、差戻し後の再申請
* `approval.approve`: 承認、却下、差戻し、承認済み依頼の使用
