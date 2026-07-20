# 第11段階 進行確認

## 確認日

2026-05-25

## 11-1 横断検索API

取引先、商品、製造ロット、受注、出荷指示、品出、出荷伝票、請求、入金、帳票出力履歴、運用ジョブ履歴を横断検索するAPIを追加した。

実装済み:

* `SearchService::crossSearch`
* `SearchController@index`
* `GET /api/v1/search`

確認内容:

* 検索語 `q` と取得件数 `limit` を指定できる。
* 検索APIは参照専用であり、伝票状態、税額、在庫、売掛、月次集計を更新しない。

## 11-2 取引別一覧検索

取引種別、状態、取引先、商品、日付範囲、検索語による取引一覧検索を追加した。

実装済み:

* `SearchService::transactions`
* `SearchController@transactions`
* `GET /api/v1/search/transactions`

対象:

* 受注
* 出荷指示
* 品出
* 出荷伝票
* 請求
* 入金予定
* 入金

## 11-3 状態別一覧

対象種別ごとの状態件数と直近明細を確認するAPIを追加した。

実装済み:

* `SearchService::statuses`
* `SearchController@statuses`
* `GET /api/v1/search/statuses`

対象:

* 受注、出荷指示、品出、出荷伝票、請求、入金予定、入金
* 帳票出力履歴
* 運用ジョブ履歴

## 11-4 関連検索

取引先、商品、製造ロットを軸に、関連する業務データをたどるAPIを追加した。

実装済み:

* `SearchService::relations`
* `SearchController@relations`
* `GET /api/v1/search/relations`

対象:

* 受注
* 出荷指示
* 品出
* 出荷伝票
* 請求
* 在庫移動
* ロット引当

## 11-5 未処理一覧

実務上の処理残を確認するAPIを追加した。

実装済み:

* `SearchService::pending`
* `SearchController@pending`
* `GET /api/v1/search/pending`

対象:

* 未指示受注
* 未品出指示
* 未伝票化品出
* ドラフト出荷
* ドラフト請求
* 未回収入金予定
* 実行中ジョブ

## 11-6 要確認一覧

運用確認が必要な履歴を確認するAPIを追加した。

実装済み:

* `SearchService::reviewRequired`
* `SearchController@reviewRequired`
* `GET /api/v1/search/review-required`

対象:

* 失敗ジョブ
* 失敗帳票
* 取消済み受注、出荷、請求、入金

## 11-7 締め対象一覧

対象年月の締め前確認に使うAPIを追加した。

実装済み:

* `SearchService::closingTargets`
* `SearchController@closingTargets`
* `GET /api/v1/search/closing-targets`

対象:

* 対象月の確定済み在庫移動
* 酒税対象出荷
* 消費税対象請求
* 売掛対象入金予定
* 在庫月次残高
* 売掛月次残高
* 酒税月次申告
* 消費税月次申告

## 11-8 操作履歴・監査ログ検索

監査ログを検索するAPIを追加した。

実装済み:

* `SearchService::auditLogs`
* `SearchController@auditLogs`
* `GET /api/v1/search/audit-logs`

検索条件:

* イベント
* 対象テーブル
* 対象ID
* ユーザー
* リクエストID
* 日付範囲
* 検索語

## 11-9 整合チェック

第11段階の検索・一覧APIについて、マニフェスト、APIルート、権限、Feature Testの整合を確認した。

確認内容:

* 検索APIはすべて `search.view` 権限で保護されている。
* ControllerはRequest受け取りとResponse返却に限定し、検索処理は `SearchService` に集約している。
* 検索APIは参照専用であり、状態遷移、税額計算、在庫計算、売掛計算、月次集計を実行しない。
* `SearchApiTest` で横断検索、取引別一覧、状態別一覧、関連検索、未処理一覧、要確認一覧、締め対象一覧、監査ログ検索、権限不足を確認している。
* マニフェストへ第11段階の実装内容を反映済み。

残課題:

* 画面そのものの作成は未実装。第11段階ではAPI入口までを完了範囲とする。
* PostgreSQL全文検索インデックスや検索専用VIEWは、データ量が増えて性能要件が明確になった段階で追加検討する。
