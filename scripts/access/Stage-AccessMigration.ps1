[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $SourcePath
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$exporter = Join-Path $PSScriptRoot 'Export-AccessMigrationPackage.ps1'
$packagePath = & $exporter -SourcePath $SourcePath -PassThru

if ([string]::IsNullOrWhiteSpace($packagePath)) {
    throw 'The Access extractor did not return a package path.'
}

$relativePackagePath = $packagePath.Substring($projectRoot.Length).TrimStart('\', '/') -replace '\\', '/'
Push-Location $projectRoot
try {
    & docker compose exec -T app php artisan aikura:access-stage $relativePackagePath --validate
    if ($LASTEXITCODE -ne 0) {
        throw "Access staging or validation failed with exit code $LASTEXITCODE."
    }
}
finally {
    Pop-Location
}
