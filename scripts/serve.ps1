$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$port = 8000
$hostAddress = '127.0.0.1'
$pidFile = Join-Path $projectRoot 'storage\logs\php-server.pid'
$outLog = Join-Path $projectRoot 'storage\logs\php-server.out.log'
$errLog = Join-Path $projectRoot 'storage\logs\php-server.err.log'

$tmpUploadDir = Join-Path $env:TEMP 'famille-platform-upload-tmp'
$tmpUploadDirForPhp = ($tmpUploadDir -replace '\\','/')

New-Item -ItemType Directory -Path (Join-Path $projectRoot 'storage\logs') -Force | Out-Null
New-Item -ItemType Directory -Path $tmpUploadDir -Force | Out-Null

$existing = Get-NetTCPConnection -LocalAddress $hostAddress -LocalPort $port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
if ($existing) {
    try { Stop-Process -Id $existing.OwningProcess -Force -ErrorAction SilentlyContinue } catch {}
}

# Clear cached config/routes/views to reduce "works once then hangs" issues
php artisan optimize:clear | Out-Null

# Let the PHP built-in server use multiple workers when supported.
# (On Windows it logs "forking is not supported" and is ignored.)
if (-not $IsWindows) {
    $env:PHP_CLI_SERVER_WORKERS = '4'
} else {
    Remove-Item Env:PHP_CLI_SERVER_WORKERS -ErrorAction SilentlyContinue
}

$serverProcess = Start-Process -FilePath php -WorkingDirectory $projectRoot -ArgumentList @(
    '-d', 'upload_max_filesize=3G',
    '-d', 'post_max_size=3G',
    '-d', ("upload_tmp_dir=$tmpUploadDirForPhp"),
    '-d', ("sys_temp_dir=$tmpUploadDirForPhp"),
    '-d', 'max_execution_time=0',
    '-d', 'max_input_time=-1',
    '-S', "$hostAddress`:$port",
    '-t', 'public',
    'server.php'
) -RedirectStandardOutput $outLog -RedirectStandardError $errLog -PassThru

Set-Content -Path $pidFile -Value $serverProcess.Id -NoNewline

Write-Host "Server started: http://$hostAddress`:$port (PID $($serverProcess.Id))"
Write-Host "Logs: $outLog"
Write-Host "Errors: $errLog"
