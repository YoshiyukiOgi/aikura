# 第8段階 進行確認

## 確認日

2026-05-24

## 8-1 Controller/API基礎

画面・API・権限制御へ進むためのAPI入口を追加した。

実装済み:

* `routes/api.php`
* `bootstrap/app.php` のAPIルート登録
* `ApiController`
* `SystemController`
* `ApiRouteCatalogService`
* `ApiFoundationTest`

方針:

* ControllerはRequest受け取り、Service呼び出し、Response返却に限定する。
* Controllerに税額、酒税、価格、在庫、売掛、月次残高の確定計算を書かない。
* 第8段階の各APIは既存Serviceを呼ぶ入口として実装する。
* 権限制御の本接続は8-2で行う。

## 8-2 権限制御接続

API入口に既存のユーザー、ロール、権限を接続した。

実装済み:

* `RequirePermission`
* `permission` middleware alias
* API groupへの `AssignRequestId`
* `AuditLogController`
* `/api/v1/audit-logs`
* `ApiPermissionMiddlewareTest`

確認内容:

* 未ログインユーザーは保護APIへアクセスできない。
* 権限を持たないユーザーは保護APIへアクセスできない。
* 無効ユーザーはロールを持っていても保護APIへアクセスできない。
* 必要権限を持つ有効ユーザーは保護APIへアクセスできる。

方針:

* Controllerに権限判断を直接書かず、MiddlewareからAuthorizationServiceへ委譲する。
* 重要操作APIは、8-3以降で個別の権限コードを割り当てる。

## 8-3 受注API基礎

受注のAPI入口を追加した。

実装済み:

* `SalesOrderController`
* `StoreSalesOrderRequest`
* `CancelSalesOrderRequest`
* `/api/v1/sales-orders`
* `/api/v1/sales-orders/{sales_order}`
* `/api/v1/sales-orders/{sales_order}/cancel`
* `sales_order.view`
* `sales_order.create`
* `sales_order.cancel`
* `SalesOrderApiTest`

確認内容:

* 権限を持つユーザーが受注を作成、参照、一覧表示、取消できる。
* 権限を持たないユーザーは受注を作成できない。
* 受注作成APIは明細必須を検証する。

方針:

* APIはCreateSalesOrderServiceとCancelSalesOrderServiceを呼ぶ入口に限定する。
* Controllerに受注番号採番、状態遷移、監査ログ作成の業務判断を書かない。

## 8-4 出荷指示API基礎

出荷指示のAPI入口を追加した。

実装済み:

* `ShipmentInstructionController`
* `StoreShipmentInstructionRequest`
* `CancelShipmentInstructionRequest`
* `/api/v1/shipment-instructions`
* `/api/v1/shipment-instructions/{shipment_instruction}`
* `/api/v1/shipment-instructions/{shipment_instruction}/cancel`
* `shipment_instruction.view`
* `shipment_instruction.create`
* `shipment_instruction.cancel`
* `ShipmentInstructionApiTest`

確認内容:

* 権限を持つユーザーが出荷指示を作成、参照、一覧表示、取消できる。
* 出荷指示作成時に受注明細の未出荷残と受注状態がService経由で更新される。
* 出荷指示取消時に受注明細の未出荷残と受注状態がService経由で戻る。
* 権限を持たないユーザーは出荷指示を作成できない。
* 出荷指示作成APIは明細必須を検証する。

方針:

* APIはCreateShipmentInstructionServiceとCancelShipmentInstructionServiceを呼ぶ入口に限定する。
* Controllerに出荷指示番号採番、受注残更新、状態遷移、監査ログ作成の業務判断を書かない。

## 8-5 品出API基礎

品出のAPI入口を追加した。

実装済み:

* `ShipmentPickController`
* `StoreShipmentPickRequest`
* `CancelShipmentPickRequest`
* `/api/v1/shipment-picks`
* `/api/v1/shipment-picks/{shipment_pick}`
* `/api/v1/shipment-picks/{shipment_pick}/cancel`
* `shipment_pick.view`
* `shipment_pick.create`
* `shipment_pick.cancel`
* `ShipmentPickApiTest`

確認内容:

* 権限を持つユーザーが品出を作成、参照、一覧表示、取消できる。
* 品出作成時に出荷指示明細の品出済数量と出荷指示状態がService経由で更新される。
* 品出取消時に出荷指示明細の品出済数量と出荷指示状態がService経由で戻る。
* 権限を持たないユーザーは品出を作成できない。
* 品出作成APIは明細必須を検証する。

方針:

* APIはPickShipmentInstructionServiceとCancelShipmentPickServiceを呼ぶ入口に限定する。
* Controllerに品出番号採番、品出済数量更新、状態遷移、監査ログ作成の業務判断を書かない。
* 品出作成時点では在庫移動、請求、売掛を発生させない。

## 8-6 出荷伝票API基礎

出荷伝票のAPI入口を追加した。

実装済み:

* `ShipmentController`
* `StoreShipmentRequest`
* `ShipmentActionReasonRequest`
* `CancelShipmentRequest`
* `/api/v1/shipments`
* `/api/v1/shipments/{shipment}`
* `/api/v1/shipments/{shipment}/price`
* `/api/v1/shipments/{shipment}/confirm`
* `/api/v1/shipments/{shipment}/cancel`
* `shipment.view`
* `shipment.create`
* `shipment.price`
* `shipment.confirm`
* `shipment.cancel`
* `ShipmentApiTest`

確認内容:

* 権限を持つユーザーが出荷伝票ドラフトを作成、価格適用、確定、参照、一覧表示、取消できる。
* 出荷伝票確定時に価格、消費税、酒税見込額、在庫移動がService経由で処理される。
* 出荷伝票取消時に状態、取消理由、在庫戻しがService経由で処理される。
* 権限を持たないユーザーは出荷伝票を作成できない。
* 出荷伝票作成APIは明細必須を検証する。

方針:

* APIはCreateDraftShipmentService、CreateDraftShipmentFromPickService、ApplyDraftShipmentPricingService、ConfirmShipmentService、CancelShipmentServiceを呼ぶ入口に限定する。
* Controllerに出荷伝票番号採番、価格解決、税スナップショット、酒税見込額、在庫移動、監査ログ作成の業務判断を書かない。
* 確定前の出荷伝票ドラフト作成だけでは、在庫移動、請求、売掛を発生させない。

## 8-7 在庫API基礎

在庫とロット引当のAPI入口を追加した。

実装済み:

* `InventoryController`
* `ReserveInventoryRequest`
* `AllocateInventoryLotRequest`
* `/api/v1/inventory/stock`
* `/api/v1/inventory/lot-stock`
* `/api/v1/inventory/reserve`
* `/api/v1/inventory/allocate`
* `inventory.view`
* `inventory.reserve`
* `inventory.allocate`
* `InventoryApiTest`

確認内容:

* 権限を持つユーザーが現在庫、ロット在庫を参照できる。
* 権限を持つユーザーが出荷明細に在庫予約できる。
* 権限を持つユーザーが出荷明細にロット引当できる。
* 在庫予約とロット引当後の現在庫、予約数量、引当数量、利用可能数量がService経由で反映される。
* 権限を持たないユーザーは在庫予約できない。
* 在庫予約APIは数量必須を検証する。

方針:

* APIはCurrentStockBalanceService、LotStockBalanceService、ReserveShipmentLineStockService、AllocateShipmentLineLotServiceを呼ぶ入口に限定する。
* Controllerに現在庫、ロット在庫、予約可能数量、引当可能数量の計算を書かない。
* 在庫予約とロット引当では在庫移動を発生させず、在庫移動は出荷確定時に発生させる。

## 8-8 請求・入金API基礎

請求・入金のAPI入口を追加した。

実装済み:

* `BillingController`
* `StoreInvoiceRequest`
* `StorePaymentScheduleRequest`
* `RegisterPaymentRequest`
* `CancelBillingRequest`
* `/api/v1/billing/invoices`
* `/api/v1/billing/invoices/{invoice}`
* `/api/v1/billing/invoices/{invoice}/confirm`
* `/api/v1/billing/invoices/{invoice}/cancel`
* `/api/v1/billing/payment-schedules`
* `/api/v1/billing/payments`
* `/api/v1/billing/payments/{payment}/cancel`
* `/api/v1/billing/receivables`
* `billing.view`
* `billing.invoice.create`
* `billing.invoice.confirm`
* `billing.invoice.cancel`
* `billing.payment_schedule.create`
* `billing.payment.create`
* `billing.payment.cancel`
* `BillingApiTest`

確認内容:

* 権限を持つユーザーが請求ドラフト作成、請求確定、入金予定作成、入金登録、入金取消、請求参照、売掛残高照会を実行できる。
* 請求確定時の請求金額と消費税、入金予定作成、入金登録後の売掛残高がService経由で処理される。
* 入金取消時に入金状態と売掛残高がService経由で戻る。
* 権限を持たないユーザーは請求ドラフトを作成できない。
* 請求ドラフト作成APIは請求日必須を検証する。

方針:

* APIはCreateInvoiceDraftService、ConfirmInvoiceService、CancelInvoiceService、CreatePaymentScheduleService、RegisterPaymentService、CancelPaymentService、ReceivableBalanceServiceを呼ぶ入口に限定する。
* Controllerに請求金額、消費税、入金予定額、入金消込、売掛残高の計算を書かない。
* 請求・入金の状態遷移、取消、売掛残高更新はService層の責務とする。

## 8-9 税務API基礎

酒税月次申告と消費税月次申告のAPI入口を追加した。

実装済み:

* `TaxController`
* `StoreTaxMonthlyFilingRequest`
* `/api/v1/tax/liquor-monthly-filings`
* `/api/v1/tax/liquor-monthly-filings/{filing}`
* `/api/v1/tax/liquor-monthly-filings/{filing}/confirm`
* `/api/v1/tax/consumption-monthly-filings`
* `/api/v1/tax/consumption-monthly-filings/{filing}`
* `/api/v1/tax/consumption-monthly-filings/{filing}/confirm`
* `tax.view`
* `tax.liquor_filing.create`
* `tax.liquor_filing.confirm`
* `tax.consumption_filing.create`
* `tax.consumption_filing.confirm`
* `TaxApiTest`

確認内容:

* 権限を持つユーザーが酒税月次申告ドラフト作成、確定、詳細参照を実行できる。
* 権限を持つユーザーが消費税月次申告ドラフト作成、確定、一覧参照を実行できる。
* 酒税月次申告では移出数量、KL換算、酒税見込額、確定額がService経由で処理される。
* 消費税月次申告では課税対象額、消費税額、税込額、確定税額がService経由で処理される。
* 権限を持たないユーザーは酒税月次申告ドラフトを作成できない。
* 税務申告ドラフト作成APIは年度必須を検証する。

方針:

* APIはCreateLiquorTaxMonthlyFilingDraftService、ConfirmLiquorTaxMonthlyFilingService、CreateConsumptionTaxMonthlyFilingDraftService、ConfirmConsumptionTaxMonthlyFilingServiceを呼ぶ入口に限定する。
* Controllerに酒税移出集計、酒税額、消費税集計、消費税額、確定額の計算を書かない。
* 税務申告確定後の業務データ更新禁止は既存Service層の責務とする。

## 8-10 月次締めAPI基礎

在庫月次残高と売掛月次残高のAPI入口を追加した。

実装済み:

* `MonthlyClosingController`
* `StoreMonthlyClosingRequest`
* `/api/v1/monthly-closing/stock-balances`
* `/api/v1/monthly-closing/stock-balances/{year}/{month}/confirm`
* `/api/v1/monthly-closing/receivable-balances`
* `/api/v1/monthly-closing/receivable-balances/{year}/{month}/confirm`
* `/api/v1/monthly-closing/receivable-balances/{year}/{month}/close`
* `monthly_closing.view`
* `monthly_closing.execute`
* `MonthlyClosingApiTest`

確認内容:

* 権限を持つユーザーが在庫月次残高ドラフト作成、確定、一覧参照を実行できる。
* 権限を持つユーザーが売掛月次残高ドラフト作成、確定、締め、一覧参照を実行できる。
* 在庫月次残高では期首、入庫、出庫、調整、期末数量がService経由で処理される。
* 売掛月次残高では請求予定額、入金額、未回収額、件数がService経由で処理される。
* 権限を持たないユーザーは在庫月次残高ドラフトを作成できない。
* 月次締めAPIは年度必須を検証する。

方針:

* APIはCreateStockMonthlyBalanceDraftService、ConfirmStockMonthlyBalanceService、CreateReceivableMonthlyBalanceDraftService、ConfirmReceivableMonthlyBalanceService、CloseReceivableMonthlyBalanceServiceを呼ぶ入口に限定する。
* Controllerに在庫数量、入庫数量、出庫数量、調整数量、売掛予定額、入金額、未回収額の計算を書かない。
* 締め後の業務データ更新禁止は既存Service層の責務とする。

## 8-11 入力制御・状態別操作制御

API共通の業務エラー応答を追加し、状態不整合時の画面向け応答を固定した。

実装済み:

* `bootstrap/app.php` のAPI向けDomainException JSON応答
* `ApiStateControlTest`

確認内容:

* 確定済み出荷伝票を再確定しようとした場合、APIは500ではなく409を返す。
* 確定済み請求を再確定しようとした場合、APIは500ではなく409を返す。
* 業務拒否時の `error.code` は `business_rule_violation` とする。
* 権限拒否、入力検証、通常API処理に影響しない。

方針:

* Controllerに状態遷移可否や編集可否の業務判断を書かない。
* Service層の業務例外をAPI共通の業務エラーJSONとして返す。
* 入力不備は422、未ログインは401、権限不足または無効ユーザーは403、業務状態拒否は409として画面側が区別できるようにする。
