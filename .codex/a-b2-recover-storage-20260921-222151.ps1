$ErrorActionPreference = 'Stop'

$stage = 'C:\aikura-staging\a-b2-promotion-20260921-222151'
$storageArchive = Join-Path $stage 'aikura-a-b2-20260921-222151-storage.tar.gz'
$projects = @(Get-ChildItem -LiteralPath 'C:\docker' -Directory | Where-Object {
    Test-Path -LiteralPath (Join-Path $_.FullName 'docker-compose.yml')
})
if ($projects.Count -ne 1) {
    throw "Expected exactly one Docker Compose project under C:\docker; found $($projects.Count)."
}
if (-not (Test-Path -LiteralPath $storageArchive)) {
    throw "Storage archive is missing."
}

$project = $projects[0].FullName
Set-Location -LiteralPath $project
docker compose up -d --no-deps app
docker cp $storageArchive 'aikura_app:/tmp/aikura-a-b2-20260921-222151-storage.tar.gz'
docker exec aikura_app sh -lc 'find /var/www/html/storage/app -mindepth 1 -maxdepth 1 -exec rm -rf {} + && tar -xzf /tmp/aikura-a-b2-20260921-222151-storage.tar.gz -C /var/www/html/storage/app'
docker compose exec -T app php artisan migrate --force --no-interaction
docker compose up -d --no-deps nginx scheduler
