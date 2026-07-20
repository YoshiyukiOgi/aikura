[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $SourcePath,

    [string] $OutputRoot = (Join-Path $PSScriptRoot '..\..\storage\app\access-migrations'),

    [string[]] $Tables,

    [switch] $PassThru
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Convert-AccessValue {
    param([object] $Value)

    if ($null -eq $Value -or $Value -is [System.DBNull]) {
        return $null
    }

    if ($Value -is [DateTime]) {
        return $Value.ToString('yyyy-MM-ddTHH:mm:ss.fffffff', [Globalization.CultureInfo]::InvariantCulture)
    }

    if ($Value -is [byte[]]) {
        return [ordered]@{
            '__type' = 'binary'
            'base64' = [Convert]::ToBase64String($Value)
        }
    }

    return $Value
}

function Get-SafeFileName {
    param([int] $Index, [string] $TableName)

    $safe = $TableName
    foreach ($character in [IO.Path]::GetInvalidFileNameChars()) {
        $safe = $safe.Replace([string] $character, '_')
    }

    return ('{0:D3}-{1}.ndjson' -f $Index, $safe)
}

$resolvedSource = (Resolve-Path -LiteralPath $SourcePath).Path
$sourceFile = Get-Item -LiteralPath $resolvedSource
$sourceHash = (Get-FileHash -LiteralPath $resolvedSource -Algorithm SHA256).Hash.ToUpperInvariant()
$batchName = '{0}-{1}' -f (Get-Date -Format 'yyyyMMdd-HHmmss'), $sourceHash.Substring(0, 12).ToLowerInvariant()
$resolvedOutputRoot = [IO.Path]::GetFullPath($OutputRoot)
$packagePath = Join-Path $resolvedOutputRoot $batchName
$workPath = Join-Path $packagePath '.work'
$copiedDatabase = Join-Path $workPath $sourceFile.Name

New-Item -ItemType Directory -Path $workPath -Force | Out-Null
Copy-Item -LiteralPath $resolvedSource -Destination $copiedDatabase -Force

$connection = $null
$provider = $null
$tableResults = [Collections.Generic.List[object]]::new()
$totalRowCount = [int64] 0
$utf8 = [Text.UTF8Encoding]::new($false)

try {
    foreach ($candidate in @('Microsoft.ACE.OLEDB.16.0', 'Microsoft.ACE.OLEDB.12.0')) {
        try {
            $candidateConnection = [Data.OleDb.OleDbConnection]::new(
                "Provider=$candidate;Data Source=$copiedDatabase;Mode=Read;"
            )
            $candidateConnection.Open()
            $connection = $candidateConnection
            $provider = $candidate
            break
        }
        catch {
            if ($null -ne $candidateConnection) {
                $candidateConnection.Dispose()
            }
        }
    }

    if ($null -eq $connection) {
        throw 'Could not open the Access file with Microsoft ACE OLE DB 16.0 or 12.0.'
    }

    $availableTables = @(
        $connection.GetSchema('Tables') |
            Where-Object { $_.TABLE_TYPE -eq 'TABLE' -and $_.TABLE_NAME -notlike 'MSys*' } |
            ForEach-Object { [string] $_.TABLE_NAME } |
            Sort-Object
    )

    if ($null -ne $Tables -and $Tables.Count -gt 0) {
        $missingTables = @($Tables | Where-Object { $_ -notin $availableTables })
        if ($missingTables.Count -gt 0) {
            throw "Unknown Access table(s): $($missingTables -join ', ')"
        }
        $availableTables = @($availableTables | Where-Object { $_ -in $Tables })
    }

    $tableIndex = 0
    foreach ($tableName in $availableTables) {
        $tableIndex++
        $fileName = Get-SafeFileName -Index $tableIndex -TableName $tableName
        $outputFile = Join-Path $packagePath $fileName
        $command = $connection.CreateCommand()
        $command.CommandText = "SELECT * FROM [$($tableName.Replace(']', ']]'))]"
        $reader = $command.ExecuteReader()
        $schema = $reader.GetSchemaTable() | Sort-Object ColumnOrdinal
        $columns = @(
            $schema | ForEach-Object {
                [ordered]@{
                    name = [string] $_.ColumnName
                    type = [string] $_.DataType.FullName
                    nullable = [bool] $_.AllowDBNull
                    ordinal = [int] $_.ColumnOrdinal
                }
            }
        )
        $writer = [IO.StreamWriter]::new($outputFile, $false, $utf8)
        $rowCount = 0

        try {
            while ($reader.Read()) {
                $row = [ordered]@{}
                for ($columnIndex = 0; $columnIndex -lt $reader.FieldCount; $columnIndex++) {
                    $row[$reader.GetName($columnIndex)] = Convert-AccessValue $reader.GetValue($columnIndex)
                }
                $writer.WriteLine(($row | ConvertTo-Json -Compress -Depth 6))
                $rowCount++
            }
        }
        finally {
            $writer.Dispose()
            $reader.Dispose()
            $command.Dispose()
        }

        $tableResults.Add([ordered]@{
            name = $tableName
            file = $fileName
            row_count = $rowCount
            sha256 = (Get-FileHash -LiteralPath $outputFile -Algorithm SHA256).Hash.ToUpperInvariant()
            columns = $columns
        })
        $totalRowCount += $rowCount
        Write-Host ("{0}: {1:N0} rows" -f $tableName, $rowCount)
    }

    $manifest = [ordered]@{
        package_version = 1
        extractor_version = '1.0.0'
        extracted_at = (Get-Date).ToUniversalTime().ToString('o')
        provider = $provider
        source = [ordered]@{
            path = $resolvedSource
            file_name = $sourceFile.Name
            size = [int64] $sourceFile.Length
            last_modified_at = $sourceFile.LastWriteTimeUtc.ToString('o')
            sha256 = $sourceHash
        }
        table_count = $tableResults.Count
        row_count = $totalRowCount
        tables = @($tableResults)
    }

    [IO.File]::WriteAllText(
        (Join-Path $packagePath 'manifest.json'),
        ($manifest | ConvertTo-Json -Depth 10),
        $utf8
    )

    Write-Host "PACKAGE=$packagePath"
    Write-Host "SOURCE_SHA256=$sourceHash"
    if ($PassThru) {
        Write-Output $packagePath
    }
}
finally {
    if ($null -ne $connection) {
        $connection.Dispose()
    }
    if (Test-Path -LiteralPath $workPath) {
        Remove-Item -LiteralPath $workPath -Recurse -Force
    }
}
