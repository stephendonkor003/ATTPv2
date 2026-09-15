$ErrorActionPreference = 'Stop'
$BackendPort = 8000
$PortalPort = 3000
$backendRoot = Split-Path -Parent $PSScriptRoot
$portalRoot = Join-Path (Split-Path -Parent $backendRoot) 'thinktankportal'
$backendOrigin = "http://127.0.0.1:$BackendPort"
$portalOrigin = "http://localhost:$PortalPort"
$logDirectory = Join-Path $backendRoot 'storage\logs'

if (-not (Test-Path -LiteralPath (Join-Path $portalRoot 'node_modules\next\dist\bin\next'))) {
    throw "Install the portal dependencies with npm.cmd install in $portalRoot first."
}
function Get-Listener([int]$Port) {
    Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue |
        Select-Object -First 1
}

function Wait-ForHttp([string]$Url, [hashtable]$Headers = @{}) {
    $deadline = (Get-Date).AddSeconds(90)
    do {
        try {
            $response = Invoke-WebRequest -UseBasicParsing -Uri $Url -Headers $Headers -TimeoutSec 10
            if ($response.StatusCode -eq 200) { return $response }
        } catch {
            Start-Sleep -Milliseconds 500
        }
    } while ((Get-Date) -lt $deadline)
    throw "The service did not become ready at $Url. Check $logDirectory."
}

$backendListener = Get-Listener $BackendPort
if ($backendListener) {
    $backendProcess = Get-CimInstance Win32_Process -Filter "ProcessId = $($backendListener.OwningProcess)"
    if ($backendProcess.CommandLine -notlike "*$backendRoot*") {
        throw "Port $BackendPort is already used by another process. No process was stopped."
    }
} else {
    $phpExecutable = (Get-Command php.exe).Source
    Start-Process -FilePath $phpExecutable -ArgumentList @(
        'artisan', 'serve', '--host=127.0.0.1', "--port=$BackendPort", '--tries=1'
    ) -WorkingDirectory $backendRoot -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $logDirectory 'think-tank-backend.stdout.log') `
        -RedirectStandardError (Join-Path $logDirectory 'think-tank-backend.stderr.log') | Out-Null
}

$apiHeaders = @{ Accept = 'application/json'; Origin = $portalOrigin; Referer = "$portalOrigin/" }
$backend = Wait-ForHttp "$backendOrigin/api/v1/think-tank/auth/session" $apiHeaders
if (($backend.Content | ConvertFrom-Json).data.state -ne 'UNAUTHENTICATED') {
    throw 'The backend did not return the expected anonymous Think Tank session contract.'
}

$portalListener = Get-Listener $PortalPort
if (-not $portalListener) {
    $nodeExecutable = (Get-Command node.exe).Source
    $previousApiOrigin = $env:LARAVEL_API_BASE_URL
    try {
        $env:LARAVEL_API_BASE_URL = $backendOrigin
        Start-Process -FilePath $nodeExecutable -ArgumentList @(
            'node_modules/next/dist/bin/next', 'dev', '--hostname', 'localhost', '--port', "$PortalPort"
        ) -WorkingDirectory $portalRoot -WindowStyle Hidden `
            -RedirectStandardOutput (Join-Path $logDirectory 'think-tank-portal.stdout.log') `
            -RedirectStandardError (Join-Path $logDirectory 'think-tank-portal.stderr.log') | Out-Null
    } finally {
        if ($null -eq $previousApiOrigin) {
            Remove-Item Env:LARAVEL_API_BASE_URL -ErrorAction SilentlyContinue
        } else {
            $env:LARAVEL_API_BASE_URL = $previousApiOrigin
        }
    }
}

$null = Wait-ForHttp "$portalOrigin/login"
$portalApi = Wait-ForHttp "$portalOrigin/api/v1/think-tank/auth/session" $apiHeaders
if (($portalApi.Content | ConvertFrom-Json).data.state -ne 'UNAUTHENTICATED') {
    throw 'The portal did not return the expected Think Tank API session contract.'
}

Write-Host "Laravel: $backendOrigin"
Write-Host "Think Tank portal: $portalOrigin/login"
Write-Host 'Verified the login page and the Laravel session API through the portal.'
Write-Host 'Existing account status and email settings are preserved.'
