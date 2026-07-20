# 第0段階 総合確認

## 確認日

2026-05-22

## 対象

第0段階で作成した以下の基盤を確認した。

* Docker / Laravel / PostgreSQL / Redis
* 基礎ドキュメント
* 共通DB基盤
* 監査ログ基盤
* 状態遷移基盤
* 採番基盤
* 認証・権限の最小基盤

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

### PostgreSQL

`aikura` データベースへ `aikura` ユーザーで接続できることを確認した。

### Redis

`redis-cli ping` が `PONG` を返すことを確認した。

### migration

以下のmigrationが `Ran` であることを確認した。

* `2026_05_22_000001_create_employees_table`
* `2026_05_22_000002_create_users_table`
* `2026_05_22_000003_create_roles_and_permissions_tables`
* `2026_05_22_000004_create_audit_logs_table`
* `2026_05_22_000005_create_number_sequences_table`

### seed

基本ロール・権限seedとして以下を確認した。

* roles: 1
* permissions: 11
* permission_role: 11

### テスト

`php artisan test` の結果は以下。

```text
Tests: 20 passed (76 assertions)
```

## 完了判定

第0段階は完了とする。

次段階では、第1段階のマスタと出荷伝票MVPへ進む。

