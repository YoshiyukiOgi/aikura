# 開発ルール

## 1. 位置づけ

この文書は、Laravel実装、migration、Service、Controller、テスト、Codexへの依頼単位に関する開発ルールを定義する。

上位規約は `docs/manifest.md` とする。

## 2. 実装順

大きな業務機能を一括実装しない。

実装は以下の順で進める。

1. DB定義
2. Model
3. Service
4. Feature Test
5. Controller
6. Request validation
7. ViewまたはAPI
8. 帳票または非同期処理

UIより先に、DB、Service、テストを固める。

## 3. レイヤー責務

### 3-1. Controller

Controllerは薄く保つ。

Controllerで行ってよいことは以下とする。

* Request受け取り
* Validation呼び出し
* Service呼び出し
* Response返却

Controllerに業務ロジック、税計算、酒税計算、価格決定、在庫更新、状態遷移を直接書いてはならない。

### 3-2. Service

Serviceは業務処理の中心とする。

Serviceが担当するものは以下とする。

* 状態遷移
* 価格決定
* 税計算
* 酒税計算
* 在庫更新
* 請求確定
* 入金消込
* 月次締め
* 監査ログ連携

重要なServiceはトランザクション境界を明確にする。

### 3-3. Model

Modelには単純な関連、スコープ、型変換を置く。

複雑な業務判断をModelへ集中させない。

### 3-4. Repository

Repositoryは必要になった場合に導入する。単純なCRUDだけのために無理に作らない。

複雑な検索、帳票用取得、集計取得ではRepositoryまたはQueryクラスを検討する。

## 4. トランザクション

以下は必ずトランザクション内で行う。

* 伝票確定
* 伝票取消
* 伝票訂正
* 在庫移動
* ロット引当
* 請求確定
* 入金消込
* 月次締め
* 締解除
* 重要マスタ変更と監査ログ記録

トランザクション内で外部通信や重い帳票生成を直接行わない。

## 5. 監査ログ

重要操作では監査ログを残す。

監査ログの作成を忘れないよう、共通Serviceまたは共通Traitの導入を検討する。

監査ログが必要な操作は `docs/manifest.md` と `docs/database.md` に従う。

## 6. migration

1. migrationは小さく分ける。
2. 命名は目的が分かる名前にする。
3. 外部キー、インデックス、一意制約を明示する。
4. 金額、数量、税額、酒税は固定小数精度にする。
5. 本番データを壊す可能性がある変更は禁止する。

## 7. 命名

クラス名は業務意図が分かる名前にする。

例は以下とする。

* `ConfirmShipmentService`
* `CancelShipmentService`
* `ResolvePriceService`
* `CalculateLiquorTaxService`
* `AllocatePaymentService`
* `CloseMonthlyPeriodService`

曖昧な `ProcessService`、`CommonService`、`HelperService` を増やさない。

## 8. 例外処理

業務上の失敗とシステム障害を区別する。

例は以下とする。

* 状態遷移不可
* 権限不足
* 締め済み期間
* 在庫不足
* 価格未設定
* 税ルール未設定

業務上の失敗はユーザーが理解できるメッセージに変換する。

## 9. Codexへの依頼粒度

Codexには小単位で依頼する。

良い依頼例は以下とする。

* `customers` migration と model を作成する。
* 出荷伝票の `draft -> confirmed` 遷移Serviceを作成する。
* 価格決定ServiceのFeature Testを作成する。
* 監査ログServiceを作成する。

悪い依頼例は以下とする。

* 販売管理システム全体を作る。
* 在庫、請求、酒税、帳票を全部まとめて実装する。
* テストなしで月次締めを作る。

## 10. 禁止事項

1. Controllerに業務ロジックを直接書かない。
2. 状態を直接更新しない。
3. 税率、酒税、価格をコード固定値にしない。
4. 確定済みデータを直接更新しない。
5. テストなしで中核業務ロジックを実装しない。
6. 仕様ドキュメントにない業務判断を勝手に追加しない。

