# 第12段階 進行確認

## 確認日

2026-05-25

## 12-1 承認基盤

承認依頼と承認履歴の正本テーブル、Model、Service、APIを追加した。

実装済み:

* `approval_requests`
* `approval_request_actions`
* `ApprovalRequest`
* `ApprovalRequestAction`
* `ApprovalService`
* `ApprovalController`
* `ApprovalFlowApiTest`

API:

* `GET /api/v1/approval-requests`
* `GET /api/v1/approval-requests/{approval_request}`
* `POST /api/v1/approval-requests`
* `POST /api/v1/approval-requests/{approval_request}/approve`
* `POST /api/v1/approval-requests/{approval_request}/reject`
* `POST /api/v1/approval-requests/{approval_request}/return`
* `POST /api/v1/approval-requests/{approval_request}/resubmit`
* `POST /api/v1/approval-requests/{approval_request}/consume`

権限:

* `approval.view`
* `approval.request`
* `approval.approve`

## 12-2 伝票取消承認

`document_cancel` を承認操作種別として追加した。対象種別と対象IDにより、受注、出荷指示、品出、出荷、請求、入金取消の承認依頼を表現する。

## 12-3 締め解除承認

`closing_reopen` を承認操作種別として追加した。締め解除の実操作はまだ専用APIを持たないため、承認基盤側で対象年月や対象残高を保持できる形にした。

## 12-4 価格変更承認

`price_change` を承認操作種別として追加した。価格変更内容は `payload` に保存し、承認依頼、差戻し、再申請、承認履歴で追跡できる。

## 12-5 税務確定承認

`tax_confirm` を承認操作種別として追加した。酒税月次申告、消費税月次申告の確定前承認として利用する。

## 12-6 権限変更承認

`role_permission_change` を承認操作種別として追加した。ロール、権限付与、権限剥奪の承認依頼として利用する。

## 12-7 差戻し・再申請

承認依頼を `returned` に差戻し、理由を保存できる。差戻し済みの依頼は、申請者が理由とpayloadを修正して `pending` に戻せる。

## 12-8 承認前実行禁止

`ApprovalService::assertApprovedFor` を追加し、承認依頼ID、操作種別、対象種別、対象IDが一致し、状態が `approved` で未使用の場合だけ実行許可できるようにした。未承認、却下済み、差戻し済み、使用済み、対象不一致は業務エラーとして拒否する。

## 12-9 整合チェック

第12段階の承認フローについて、DB、Model、Service、API、権限、監査ログ、Feature Test、マニフェストの整合を確認した。

確認内容:

* 承認依頼の正本は `approval_requests`、承認履歴の正本は `approval_request_actions` とする。
* 承認APIは `approval.view`、`approval.request`、`approval.approve` の専用権限で保護する。
* 承認依頼、承認、却下、差戻し、再申請、使用済み化の履歴を保存する。
* 承認イベントは監査ログへ保存する。
* 申請者本人による自己承認は禁止する。
* 未承認、却下済み、差戻し済み、使用済み、対象不一致の承認依頼は、実行前確認で拒否する。
* 伝票取消、締め解除、価格変更、税務確定、権限変更の承認操作種別を `ApprovalService` に定義済み。
* `ApprovalFlowApiTest` で承認依頼、承認、却下、差戻し、再申請、使用済み化、権限不足、承認前実行禁止を確認している。
* マニフェストと権限方針へ第12段階の内容を反映済み。

## 残課題

既存の取消、税務確定、価格変更、締め解除、権限変更APIへ承認ID必須を強制する接続は、運用移行時の既存API互換性に影響するため段階的に行う。第12段階では、承認基盤と実行前確認機構を完成範囲とする。
