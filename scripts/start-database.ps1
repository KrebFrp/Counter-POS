param([switch]$Foreground)
$ErrorActionPreference = 'Stop'
$projectPath = Split-Path -Parent $PSScriptRoot
$databaseRoot = Join-Path $projectPath 'storage\app\private\local-mysql'
$databaseConfig = Join-Path $databaseRoot 'data\my.ini'
$xamppRoot = Split-Path -Parent (Split-Path -Parent $projectPath)
$serverPath = Join-Path $xamppRoot 'mysql\bin\mysqld.exe'
$clientPath = Join-Path $xamppRoot 'mysql\bin\mysql.exe'
if (-not (Test-Path -LiteralPath $databaseConfig)) { throw 'The recovered local database is not present. Restore its backup before starting it.' }
if (-not (Test-Path -LiteralPath $serverPath)) { throw 'The XAMPP database runtime could not be found.' }
$expectedDataDirectory = (Resolve-Path (Join-Path $databaseRoot 'data')).Path.TrimEnd('\', '/')

function Test-ProjectDatabase {
    try {
        $actualDataDirectory = & $clientPath '--protocol=tcp' '--host=127.0.0.1' '--port=3308' '--user=root' '--connect-timeout=1' '--batch' '--skip-column-names' '--execute=SELECT @@datadir' 2>$null
        if ($LASTEXITCODE -ne 0) { return $false }
    } catch {
        return $false
    }
    $actualDataDirectory = [System.IO.Path]::GetFullPath(([string]$actualDataDirectory).Trim()).TrimEnd('\', '/')
    if (-not [string]::Equals($actualDataDirectory, $expectedDataDirectory, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'Another database is using port 3308. Its data directory does not match this project.'
    }
    return $true
}

if (Test-ProjectDatabase) {
    Write-Host 'The project database is already running on port 3308.'
    return
}
$connection = New-Object System.Net.Sockets.TcpClient
try {
    $connection.Connect('127.0.0.1', 3308)
    throw 'A server is already listening on port 3308, but its identity could not be verified.'
} catch [System.Net.Sockets.SocketException] {
} finally {
    $connection.Dispose()
}
$serverArguments = @(
    ('--defaults-file=' + $databaseConfig),
    ('--basedir=' + (Join-Path $xamppRoot 'mysql')),
    ('--tmpdir=' + (Join-Path $databaseRoot 'tmp')),
    '--bind-address=127.0.0.1',
    '--console'
)
if ($Foreground) {
    & $serverPath @serverArguments
    exit $LASTEXITCODE
}
$backgroundArguments = $serverArguments | ForEach-Object { if ($_ -match '\s') { '"' + $_ + '"' } else { $_ } }
Start-Process -FilePath $serverPath -ArgumentList $backgroundArguments -WorkingDirectory $databaseRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $databaseRoot 'mysql.stdout.log') -RedirectStandardError (Join-Path $databaseRoot 'mysql.stderr.log')
$startupDeadline = (Get-Date).AddSeconds(30)
do {
    if (Test-ProjectDatabase) {
        Write-Host 'The project database is ready on port 3308.'
        return
    }
    Start-Sleep -Milliseconds 300
} while ((Get-Date) -lt $startupDeadline)
throw 'The database did not become ready. Check the database logs under storage/app/private/local-mysql.'
