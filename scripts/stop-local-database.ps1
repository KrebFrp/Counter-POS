$ErrorActionPreference = 'Stop'
$projectPath = Split-Path -Parent $PSScriptRoot
$databaseRoot = Join-Path $projectPath 'storage\app\private\local-mysql'
if (Test-Path -LiteralPath (Join-Path $databaseRoot 'data\my.ini')) {
    $xamppRoot = Split-Path -Parent (Split-Path -Parent $projectPath)
    $clientPath = Join-Path $xamppRoot 'mysql\bin\mysql.exe'
    $adminPath = Join-Path $xamppRoot 'mysql\bin\mysqladmin.exe'
    $expectedDataDirectory = (Resolve-Path (Join-Path $databaseRoot 'data')).Path.TrimEnd('\', '/')
    $actualDataDirectory = & $clientPath '--protocol=tcp' '--host=127.0.0.1' '--port=3308' '--user=root' '--connect-timeout=2' '--batch' '--skip-column-names' '--execute=SELECT @@datadir'
    if ($LASTEXITCODE -ne 0) { throw 'The local database could not be reached; no shutdown was requested.' }
    $actualDataDirectory = [System.IO.Path]::GetFullPath(([string]$actualDataDirectory).Trim()).TrimEnd('\', '/')
    if (-not [string]::Equals($actualDataDirectory, $expectedDataDirectory, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'The server on port 3308 belongs to another database; no shutdown was requested.'
    }
    & $adminPath '--protocol=tcp' '--host=127.0.0.1' '--port=3308' '--user=root' '--connect-timeout=2' '--shutdown-timeout=30' shutdown
    if ($LASTEXITCODE -ne 0) { throw 'The database did not accept a graceful shutdown. No forced termination was attempted.' }
    Write-Host 'Graceful shutdown requested for the project database.'
    return
}
& (Join-Path $projectPath '.tools\mysql-8.4.13-winx64\bin\mysqladmin.exe') --host=127.0.0.1 --port=3308 --user=root shutdown
