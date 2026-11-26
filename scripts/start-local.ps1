param([int]$Port = 8000)
$ErrorActionPreference = 'Stop'
$projectPath = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectPath
$projectPhp = Join-Path $projectPath '.tools\php\php.exe'
if (-not (Test-Path -LiteralPath $projectPhp)) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if (-not $phpCommand) { throw 'Install PHP 8.3+ before starting Counter.' }
    $projectPhp = $phpCommand.Source
}
if (Test-Path -LiteralPath (Join-Path $projectPath 'storage\app\private\local-mysql\data\my.ini')) {
    & (Join-Path $PSScriptRoot 'start-database.ps1')
} else {
    $dbConnection = New-Object System.Net.Sockets.TcpClient
    try {
        $dbConnection.Connect('127.0.0.1', 3308)
    } catch {
        $serverPath = Join-Path $projectPath '.tools\mysql-8.4.13-winx64\bin\mysqld.exe'
        $databaseConfig = Join-Path $projectPath '.tools\mysql8.ini'
        if (-not (Test-Path -LiteralPath $databaseConfig)) { throw 'Local database is not initialized. See README.md.' }
        Start-Process -FilePath $serverPath -ArgumentList "--defaults-file=$databaseConfig", '--bind-address=127.0.0.1', '--console' -WorkingDirectory $projectPath -WindowStyle Hidden -RedirectStandardOutput '.tools\mysql.stdout.log' -RedirectStandardError '.tools\mysql.stderr.log'
        Start-Sleep -Seconds 2
    } finally {
        $dbConnection.Dispose()
    }
}
Write-Host "Counter is available at http://127.0.0.1:$Port"
$webConnection = New-Object System.Net.Sockets.TcpClient
try {
    $webConnection.Connect('127.0.0.1', $Port)
    Write-Host 'A web server is already running on this port. Open the URL above.'
    return
} catch {
    # The port is available for the development server.
} finally {
    $webConnection.Dispose()
}
& $projectPhp artisan serve --host=127.0.0.1 --port=$Port
