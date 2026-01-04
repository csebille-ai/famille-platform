$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$port = 8000
$hostAddress = '127.0.0.1'
$pidFile = Join-Path $projectRoot 'storage\logs\php-server.pid'

$stopped = $false

if (Test-Path $pidFile) {
    $serverPid = (Get-Content $pidFile -Raw).Trim()
    if ($serverPid) {
        try {
            Stop-Process -Id ([int]$serverPid) -Force -ErrorAction SilentlyContinue
            $stopped = $true
        } catch {}
    }
    Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
}

if (-not $stopped) {
    $conn = Get-NetTCPConnection -LocalAddress $hostAddress -LocalPort $port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($conn) {
        try { Stop-Process -Id $conn.OwningProcess -Force -ErrorAction SilentlyContinue } catch {}
        $stopped = $true
    }
}

if ($stopped) {
    Write-Host "Server stopped on $hostAddress`:$port"
} else {
    Write-Host "No server found on $hostAddress`:$port"
}
