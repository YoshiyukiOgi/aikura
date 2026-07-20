# AI蔵 販売管理システム

酒蔵向け販売管理システムのLaravel + PostgreSQL + Redis基盤です。

## 初回起動

Docker、PHP、Composerをローカルに直接入れず、Docker上で動かす前提です。

```powershell
docker compose build
docker compose run --rm app composer install
Copy-Item .env.example .env
docker compose run --rm app php artisan key:generate
docker compose up -d
```

起動後、以下を開きます。

```text
http://localhost:8080
```

## テスト

```powershell
docker compose run --rm test php artisan test
```

## この段階の対象

第0段階の1として、以下だけを対象にしています。

* Laravel最小構成
* Docker Compose
* nginx
* php-fpm
* PostgreSQL
* Redis
* Feature Test実行環境

業務テーブル、認証、権限、監査ログ、状態遷移は次段階で作成します。
