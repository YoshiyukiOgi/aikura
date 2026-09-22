[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-fA-F]{7,64}$')]
    [string] $GitCommit,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-fA-F]{64}$')]
    [string] $B1SourceSha256,

    [Parameter(Mandatory = $true)]
    [ValidateRange(1, [int]::MaxValue)]
    [int] $MigrationBatchId,

    [Parameter(Mandatory = $true)]
    [string] $DatabaseDumpPath,

    [Parameter(Mandatory = $true)]
    [string] $StorageArchivePath,

    [Parameter(Mandatory = $true)]
    [string] $ApplicationArchivePath,

    [ValidateSet('test', 'production-candidate', 'production')]
    [string] $Target = 'test',

    [string] $OutputRoot = (Join-Path $PSScriptRoot '..\\..\\storage\\app\\migration-releases')
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Get-ArtifactRecord {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path
    )

    $resolved = (Resolve-Path -LiteralPath $Path).Path
    $item = Get-Item -LiteralPath $resolved
    if ($item.PSIsContainer) {
        throw "成果物はファイルを指定してください: $resolved"
    }

    return [ordered]@{
        path = $resolved
        size = [int64] $item.Length
        last_modified_at = $item.LastWriteTimeUtc.ToString('o')
        sha256 = (Get-FileHash -LiteralPath $resolved -Algorithm SHA256).Hash.ToUpperInvariant()
    }
}

$releaseId = 'brewery-b1-a-b2-{0}-{1}' -f (Get-Date -Format 'yyyyMMdd-HHmmss'), $GitCommit.Substring(0, 7).ToLowerInvariant()
$outputDirectory = Join-Path ([IO.Path]::GetFullPath($OutputRoot)) $releaseId
New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null

$manifest = [ordered]@{
    package = '酒蔵出荷管理システムのB1-A-B2移行'
    package_version = 1
    release_id = $releaseId
    created_at = (Get-Date).ToUniversalTime().ToString('o')
    target = $Target
    b1 = [ordered]@{
        source_sha256 = $B1SourceSha256.ToUpperInvariant()
        migration_batch_id = $MigrationBatchId
    }
    a = [ordered]@{
        git_commit = $GitCommit.ToLowerInvariant()
        database_dump = Get-ArtifactRecord -Path $DatabaseDumpPath
        storage_archive = Get-ArtifactRecord -Path $StorageArchivePath
        application_archive = Get-ArtifactRecord -Path $ApplicationArchivePath
    }
    b2 = [ordered]@{
        backup_required = $Target -ne 'test'
        promotion_type = 'complete-replacement'
        notes = 'B2固有の環境設定・秘密情報はAの成果物で上書きしない。'
    }
}

$manifestPath = Join-Path $outputDirectory 'release-manifest.json'
[IO.File]::WriteAllText(
    $manifestPath,
    ($manifest | ConvertTo-Json -Depth 10),
    [Text.UTF8Encoding]::new($false)
)

Write-Host "RELEASE_ID=$releaseId"
Write-Host "MANIFEST=$manifestPath"
Write-Host "B2_BACKUP_REQUIRED=$($manifest.b2.backup_required)"
