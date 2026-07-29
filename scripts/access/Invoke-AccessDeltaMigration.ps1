[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('Plan', 'Apply')]
    [string] $Mode,

    [string] $SourcePath,

    [int] $BatchId,

    [int] $BaselineBatchId
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))

Push-Location $projectRoot
try {
    if ($Mode -eq 'Apply') {
        if ($BatchId -le 0) {
            throw 'Apply mode requires -BatchId.'
        }

        & docker compose exec -T app php artisan aikura:access-delta-apply $BatchId --yes
        if ($LASTEXITCODE -ne 0) {
            throw "Access delta apply failed with exit code $LASTEXITCODE."
        }

        return
    }

    if ([string]::IsNullOrWhiteSpace($SourcePath)) {
        throw 'Plan mode requires -SourcePath.'
    }

    $exporter = Join-Path $PSScriptRoot 'Export-AccessMigrationPackage.ps1'
    $packagePath = & $exporter -SourcePath $SourcePath -PassThru
    if ([string]::IsNullOrWhiteSpace($packagePath)) {
        throw 'The Access extractor did not return a package path.'
    }

    $relativePackagePath = $packagePath.Substring($projectRoot.Length).TrimStart('\', '/') -replace '\\', '/'
    $stageOutput = @(& docker compose exec -T app php artisan aikura:access-stage $relativePackagePath --validate 2>&1)
    $stageOutput | ForEach-Object { Write-Host $_ }
    if ($LASTEXITCODE -ne 0) {
        throw "Access staging or validation failed with exit code $LASTEXITCODE."
    }

    $batchMatch = [regex]::Match(($stageOutput -join "`n"), 'batch_id=(\d+)')
    if (-not $batchMatch.Success) {
        throw 'Could not determine the staged Access batch ID.'
    }

    $newBatchId = [int] $batchMatch.Groups[1].Value
    $planArguments = @('compose', 'exec', '-T', 'app', 'php', 'artisan', 'aikura:access-delta-plan', $newBatchId)
    if ($BaselineBatchId -gt 0) {
        $planArguments += "--baseline=$BaselineBatchId"
    }

    & docker @planArguments
    if ($LASTEXITCODE -ne 0) {
        throw "Access delta planning failed with exit code $LASTEXITCODE."
    }

    Write-Host "Review the CSV report, then apply with:"
    Write-Host ".\scripts\access\Invoke-AccessDeltaMigration.ps1 -Mode Apply -BatchId $newBatchId"
}
finally {
    Pop-Location
}
