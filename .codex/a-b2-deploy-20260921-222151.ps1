$ErrorActionPreference = 'Stop'

$stage = 'C:\aikura-staging\a-b2-promotion-20260921-222151'
$projects = @(Get-ChildItem -LiteralPath 'C:\docker' -Directory | Where-Object {
    Test-Path -LiteralPath (Join-Path $_.FullName 'docker-compose.yml')
})
if ($projects.Count -ne 1) {
    throw "Expected exactly one Docker Compose project under C:\docker; found $($projects.Count)."
}
$project = $projects[0].FullName
$deployment = Join-Path $stage 'app'
$appArchive = Join-Path $stage 'aikura-a-b2-20260921-222151-app.zip'
$databaseDump = Join-Path $stage 'aikura-a-b2-20260921-222151.dump'
$storageArchive = Join-Path $stage 'aikura-a-b2-20260921-222151-storage.tar.gz'
$storagePath = Join-Path $project 'storage\app'

foreach ($path in @($stage, $project, $appArchive, $databaseDump, $storageArchive)) {
    if (-not (Test-Path -LiteralPath $path)) {
        throw "必要なパスがありません: $path"
    }
}

Remove-Item -LiteralPath $deployment -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path $deployment -Force | Out-Null
Expand-Archive -LiteralPath $appArchive -DestinationPath $deployment -Force

Set-Location -LiteralPath $project
docker compose stop app nginx scheduler

& robocopy $deployment $project /E /XD storage .git .h5i-ctx docker /XF .env docker-compose.yml
if ($LASTEXITCODE -gt 7) {
    throw "アプリ配置に失敗しました。robocopy exit code: $LASTEXITCODE"
}

docker cp $databaseDump "aikura_postgres:/tmp/aikura-a-b2-20260921-222151.dump"
docker exec aikura_postgres sh -lc 'dropdb -U aikura --force aikura && createdb -U aikura aikura && pg_restore -U aikura -d aikura --no-owner --no-privileges /tmp/aikura-a-b2-20260921-222151.dump'

Remove-Item -LiteralPath $storagePath -Recurse -Force
New-Item -ItemType Directory -Path $storagePath -Force | Out-Null
tar.exe -xzf $storageArchive -C $storagePath
if ($LASTEXITCODE -ne 0) {
    throw "添付・帳票ファイルの展開に失敗しました。tar exit code: $LASTEXITCODE"
}

docker compose up -d --no-deps app nginx
docker compose exec -T app php artisan migrate --force --no-interaction
docker compose up -d --no-deps scheduler
