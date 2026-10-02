[CmdletBinding()]
param(
    [ValidateRange(1, 65535)]
    [int]$BackendPort = 8000,

    [ValidateRange(1, 65535)]
    [int]$PortalPort = 3000,

    [string]$PhpExecutable = ''
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Resolve-PortOverride([string]$Name, [int]$Fallback) {
    $rawValue = [Environment]::GetEnvironmentVariable($Name, 'Process')
    if ([string]::IsNullOrWhiteSpace($rawValue)) {
        return $Fallback
    }

    $parsedValue = 0
    if (-not [int]::TryParse($rawValue, [ref]$parsedValue) -or $parsedValue -lt 1 -or $parsedValue -gt 65535) {
        throw "$Name must be an integer between 1 and 65535."
    }

    return $parsedValue
}

if (-not $PSBoundParameters.ContainsKey('BackendPort')) {
    $BackendPort = Resolve-PortOverride 'THINK_TANK_BACKEND_PORT' $BackendPort
}
if (-not $PSBoundParameters.ContainsKey('PortalPort')) {
    $PortalPort = Resolve-PortOverride 'THINK_TANK_PORTAL_PORT' $PortalPort
}
if ($BackendPort -eq $PortalPort) {
    throw 'BackendPort and PortalPort must be different.'
}

$backendRoot = Split-Path -Parent $PSScriptRoot
$portalRoot = Join-Path (Split-Path -Parent $backendRoot) 'thinktankportal'
$backendOrigin = "http://127.0.0.1:$BackendPort"
$portalOrigin = "http://localhost:$PortalPort"
$logDirectory = Join-Path $backendRoot 'storage\logs'
$backendLogName = if ($BackendPort -eq 8000) { 'think-tank-backend' } else { "think-tank-backend-$BackendPort" }
$portalLogName = if ($PortalPort -eq 3000) { 'think-tank-portal' } else { "think-tank-portal-$PortalPort" }
$backendStandardOutput = Join-Path $logDirectory "$backendLogName.stdout.log"
$backendStandardError = Join-Path $logDirectory "$backendLogName.stderr.log"
$portalStandardOutput = Join-Path $logDirectory "$portalLogName.stdout.log"
$portalStandardError = Join-Path $logDirectory "$portalLogName.stderr.log"

if (-not (Test-Path -LiteralPath (Join-Path $portalRoot 'node_modules\next\dist\bin\next'))) {
    throw "Install the portal dependencies with npm.cmd install in $portalRoot first."
}

function Test-TcpListener([string]$Address, [int]$Port) {
    try {
        $addresses = [System.Net.Dns]::GetHostAddresses($Address)
    } catch {
        return $false
    }

    foreach ($resolvedAddress in $addresses) {
        $client = [System.Net.Sockets.TcpClient]::new($resolvedAddress.AddressFamily)
        try {
            $connection = $client.ConnectAsync($resolvedAddress, $Port)
            if ($connection.Wait(1500) -and $client.Connected) {
                return $true
            }
        } catch {
            # Try the next resolved IPv4 or IPv6 address.
        } finally {
            $client.Dispose()
        }
    }

    return $false
}

function Get-TcpListenerProcessIds([int]$Port) {
    $processIds = @()
    try {
        $processIds += Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction Stop |
            Select-Object -ExpandProperty OwningProcess
    } catch {
        # Fall back to netstat when TCP ownership queries are restricted.
    }

    if (-not $processIds.Count) {
        $netstatPath = Join-Path ([Environment]::GetFolderPath('System')) 'netstat.exe'
        if (Test-Path -LiteralPath $netstatPath -PathType Leaf) {
            try {
                $pattern = "^\s*TCP\s+\S+:$Port\s+\S+\s+LISTENING\s+(?<ProcessId>\d+)\s*$"
                foreach ($line in (& $netstatPath -ano -p tcp 2>$null)) {
                    if ($line -match $pattern) {
                        $processIds += [int]$Matches.ProcessId
                    }
                }
            } catch {
                # The caller will fail closed when ownership cannot be established.
            }
        }
    }

    return @($processIds | Where-Object { $_ -gt 0 } | Select-Object -Unique)
}

function Invoke-HttpProbe([string]$Url, [hashtable]$Headers = @{}) {
    try {
        return Invoke-WebRequest -UseBasicParsing -Uri $Url -Headers $Headers -TimeoutSec 5
    } catch {
        return $null
    }
}

function Test-BackendIdentity([string]$Origin, [string]$BrowserOrigin) {
    $headers = @{
        Accept = 'application/json'
        Origin = $BrowserOrigin
        Referer = "$BrowserOrigin/"
        'X-Requested-With' = 'XMLHttpRequest'
    }
    $response = Invoke-HttpProbe "$Origin/api/v1/think-tank/auth/session" $headers
    if ($null -eq $response -or $response.StatusCode -ne 200) {
        return $false
    }

    try {
        $payload = $response.Content | ConvertFrom-Json
        return $payload.data.state -eq 'UNAUTHENTICATED'
    } catch {
        return $false
    }
}

function Test-PortalIdentity([string]$Origin) {
    $login = Invoke-HttpProbe "$Origin/login"
    if ($null -eq $login -or $login.StatusCode -ne 200 -or $login.Content -notmatch 'Think Tank Workspace') {
        return $false
    }

    $headers = @{
        Accept = 'application/json'
        Origin = $Origin
        Referer = "$Origin/"
        'X-Requested-With' = 'XMLHttpRequest'
    }
    $session = Invoke-HttpProbe "$Origin/api/v1/think-tank/auth/session" $headers
    if ($null -eq $session -or $session.StatusCode -ne 200) {
        return $false
    }

    try {
        $payload = $session.Content | ConvertFrom-Json
        return $payload.data.state -eq 'UNAUTHENTICATED'
    } catch {
        return $false
    }
}

function Wait-ForIdentity([scriptblock]$Probe, [string]$Description, [string[]]$LogPaths) {
    $deadline = (Get-Date).AddSeconds(90)
    do {
        if (& $Probe) {
            return
        }
        Start-Sleep -Milliseconds 500
    } while ((Get-Date) -lt $deadline)

    $logs = $LogPaths -join ', '
    throw "$Description did not become ready within 90 seconds. Check: $logs"
}

function Join-EnvironmentList([string]$ExistingValue, [string[]]$RequiredValues) {
    $values = @()
    if (-not [string]::IsNullOrWhiteSpace($ExistingValue)) {
        $values += $ExistingValue.Split(',') | ForEach-Object { $_.Trim() } | Where-Object { $_ }
    }
    $values += $RequiredValues | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }

    return ($values | Select-Object -Unique) -join ','
}

function Invoke-WithProcessEnvironment([hashtable]$Overrides, [scriptblock]$Action) {
    $previousValues = @{}
    foreach ($name in $Overrides.Keys) {
        $previousValues[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
        [Environment]::SetEnvironmentVariable($name, [string]$Overrides[$name], 'Process')
    }

    try {
        return & $Action
    } finally {
        foreach ($name in $Overrides.Keys) {
            [Environment]::SetEnvironmentVariable($name, $previousValues[$name], 'Process')
        }
    }
}

function Get-PhpInformation([string]$Path) {
    if ([string]::IsNullOrWhiteSpace($Path) -or -not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return $null
    }

    try {
        $rawVersion = (& $Path -r 'echo PHP_VERSION;' 2>$null | Select-Object -First 1)
        if ($rawVersion -notmatch '^(?<Version>\d+\.\d+\.\d+)') {
            return $null
        }

        return [pscustomobject]@{
            Path = (Resolve-Path -LiteralPath $Path).Path
            Version = [version]$Matches.Version
        }
    } catch {
        return $null
    }
}

function Test-CompatiblePhp($Information) {
    return $null -ne $Information -and
        $Information.Version -ge [version]'8.2.0' -and
        $Information.Version -lt [version]'8.5.0'
}

function Resolve-PhpExecutable([string]$ExplicitPath) {
    if (-not [string]::IsNullOrWhiteSpace($ExplicitPath)) {
        $explicitPhp = Get-PhpInformation $ExplicitPath
        if (-not (Test-CompatiblePhp $explicitPhp)) {
            $detected = if ($null -eq $explicitPhp) { 'unreadable' } else { $explicitPhp.Version.ToString() }
            throw "PhpExecutable must point to PHP >= 8.2 and < 8.5; detected $detected."
        }
        return $explicitPhp.Path
    }

    $candidates = @()
    $localAppData = [Environment]::GetFolderPath('LocalApplicationData')
    if (-not [string]::IsNullOrWhiteSpace($localAppData)) {
        $wingetRoot = Join-Path $localAppData 'Microsoft\WinGet\Packages'
        if (Test-Path -LiteralPath $wingetRoot) {
            $candidates += Get-ChildItem -LiteralPath $wingetRoot -Directory -Filter 'PHP.PHP.8.4*' -ErrorAction SilentlyContinue |
                ForEach-Object {
                    Get-ChildItem -LiteralPath $_.FullName -File -Filter php.exe -Recurse -ErrorAction SilentlyContinue |
                        Select-Object -First 1 -ExpandProperty FullName
                }
        }
    }

    $laragonPhpRoot = 'C:\laragon\bin\php'
    if (Test-Path -LiteralPath $laragonPhpRoot) {
        $candidates += Get-ChildItem -LiteralPath $laragonPhpRoot -Directory -Filter 'php-8.4*' -ErrorAction SilentlyContinue |
            ForEach-Object { Join-Path $_.FullName 'php.exe' }
    }

    $pathCommand = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($null -ne $pathCommand) {
        $candidates += $pathCommand.Source
    }

    $discovered = @()
    foreach ($candidate in ($candidates | Where-Object { $_ } | Select-Object -Unique)) {
        $information = Get-PhpInformation $candidate
        if ($null -eq $information) {
            continue
        }

        $discovered += $information
        if (Test-CompatiblePhp $information) {
            return $information.Path
        }
    }

    $found = if ($discovered.Count) {
        ($discovered | ForEach-Object { "$($_.Path) ($($_.Version))" }) -join '; '
    } else {
        'none'
    }
    throw "No compatible PHP runtime was found. This project requires PHP >= 8.2 and < 8.5. Found: $found. Install WinGet PHP 8.4 or pass -PhpExecutable."
}

function Test-BackendListenerPhp([int]$Port) {
    $processIds = @(Get-TcpListenerProcessIds $Port)
    if (-not $processIds.Count) {
        return [pscustomobject]@{
            Compatible = $false
            Detail = "The owning process for port $Port could not be determined."
        }
    }

    foreach ($processId in $processIds) {
        try {
            $process = Get-Process -Id $processId -ErrorAction Stop
            $processPath = $process.Path
        } catch {
            return [pscustomobject]@{
                Compatible = $false
                Detail = "The executable for listener process $processId on port $Port could not be inspected."
            }
        }

        $information = Get-PhpInformation $processPath
        if ($null -eq $information) {
            return [pscustomobject]@{
                Compatible = $false
                Detail = "Listener process $processId on port $Port is not a readable PHP CLI runtime."
            }
        }
        if (-not (Test-CompatiblePhp $information)) {
            return [pscustomobject]@{
                Compatible = $false
                Detail = "Listener process $processId on port $Port uses unsupported PHP $($information.Version); required >= 8.2 and < 8.5."
            }
        }
    }

    return [pscustomobject]@{
        Compatible = $true
        Detail = "All listener processes on port $Port use compatible PHP."
    }
}

function Get-NodeInformation([string]$Path) {
    if ([string]::IsNullOrWhiteSpace($Path) -or -not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return $null
    }

    try {
        $rawVersion = (& $Path --version 2>$null | Select-Object -First 1)
        if ($rawVersion -notmatch '^v(?<Version>\d+\.\d+\.\d+)') {
            return $null
        }

        return [pscustomobject]@{
            Path = (Resolve-Path -LiteralPath $Path).Path
            Version = [version]$Matches.Version
        }
    } catch {
        return $null
    }
}

function Resolve-NodeExecutable {
    $minimumVersion = [version]'20.9.0'
    $localAppData = [Environment]::GetFolderPath('LocalApplicationData')
    $explicitPath = [Environment]::GetEnvironmentVariable('THINK_TANK_NODE_EXECUTABLE', 'Process')
    if (-not [string]::IsNullOrWhiteSpace($explicitPath)) {
        $explicitNode = Get-NodeInformation $explicitPath
        if ($null -eq $explicitNode -or $explicitNode.Version -lt $minimumVersion) {
            throw 'THINK_TANK_NODE_EXECUTABLE must point to Node.js 20.9.0 or newer.'
        }
        return $explicitNode.Path
    }

    $pathCommand = Get-Command node.exe -ErrorAction SilentlyContinue
    if ($null -ne $pathCommand) {
        $pathNode = Get-NodeInformation $pathCommand.Source
        if ($null -ne $pathNode -and $pathNode.Version -ge $minimumVersion) {
            return $pathNode.Path
        }
    }

    $candidates = @()
    $laragonRoots = @(
        [Environment]::GetEnvironmentVariable('LARAGON_ROOT', 'Process'),
        'C:\laragon'
    ) | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | Select-Object -Unique
    foreach ($laragonRoot in $laragonRoots) {
        $nodeRoot = Join-Path $laragonRoot 'bin\nodejs'
        if (Test-Path -LiteralPath $nodeRoot) {
            $candidates += Get-ChildItem -LiteralPath $nodeRoot -Directory -Filter 'node-v24*' -ErrorAction SilentlyContinue |
                ForEach-Object { Join-Path $_.FullName 'node.exe' }
        }
    }

    if (-not [string]::IsNullOrWhiteSpace($localAppData)) {
        $wingetRoot = Join-Path $localAppData 'Microsoft\WinGet\Packages'
        if (Test-Path -LiteralPath $wingetRoot) {
            $candidates += Get-ChildItem -LiteralPath $wingetRoot -Directory -Filter 'OpenJS.NodeJS*' -ErrorAction SilentlyContinue |
                ForEach-Object {
                    Get-ChildItem -LiteralPath $_.FullName -File -Filter node.exe -Recurse -ErrorAction SilentlyContinue |
                        Select-Object -First 1 -ExpandProperty FullName
                }
        }
    }

    $programFilesNode = Join-Path ([Environment]::GetFolderPath('ProgramFiles')) 'nodejs\node.exe'
    $candidates += $programFilesNode

    $available = @(
        $candidates |
            Where-Object { $_ } |
            Select-Object -Unique |
            ForEach-Object { Get-NodeInformation $_ } |
            Where-Object { $null -ne $_ -and $_.Version -ge $minimumVersion }
    )
    $node24 = $available | Where-Object { $_.Version.Major -eq 24 } | Sort-Object Version -Descending | Select-Object -First 1
    if ($null -ne $node24) {
        return $node24.Path
    }

    $supported = $available | Sort-Object Version -Descending | Select-Object -First 1
    if ($null -ne $supported) {
        return $supported.Path
    }

    throw 'Node.js 20.9.0 or newer was not found. Install Node.js 24 or set THINK_TANK_NODE_EXECUTABLE.'
}

function Stop-OwnedProcessTree($Process, [string]$Description) {
    if ($null -eq $Process) {
        return
    }

    try {
        $processId = [int]$Process.Id
        $expectedStartTime = $Process.StartTime
        $current = Get-Process -Id $processId -ErrorAction Stop
        if ($current.StartTime -ne $expectedStartTime) {
            Write-Warning "Refused to stop PID $processId while cleaning up $Description because the PID was reused."
            return
        }

        $taskkillPath = Join-Path ([Environment]::GetFolderPath('System')) 'taskkill.exe'
        if (Test-Path -LiteralPath $taskkillPath -PathType Leaf) {
            $null = & $taskkillPath /PID $processId /T /F 2>&1
            if ($LASTEXITCODE -eq 0) {
                return
            }
        }

        $current = Get-Process -Id $processId -ErrorAction SilentlyContinue
        if ($null -ne $current -and $current.StartTime -eq $expectedStartTime) {
            Stop-Process -Id $processId -Force -ErrorAction Stop
        }
    } catch {
        $stillRunning = Get-Process -Id $Process.Id -ErrorAction SilentlyContinue
        if ($null -ne $stillRunning) {
            Write-Warning "Could not clean up $Description (PID $($Process.Id)): $($_.Exception.Message)"
        }
    }
}

$launcherMutex = [System.Threading.Mutex]::new($false, 'Local\ATTPThinkTankLocalLauncher')
$lockTaken = $false
$startedBackendProcess = $null
$startedPortalProcess = $null
$launchCompleted = $false
try {
    try {
        $lockTaken = $launcherMutex.WaitOne([TimeSpan]::FromSeconds(15))
    } catch [System.Threading.AbandonedMutexException] {
        $lockTaken = $true
    }
    if (-not $lockTaken) {
        throw 'Another Think Tank local launcher is still running. Try again after it completes.'
    }

    $backendProbe = { Test-BackendIdentity $backendOrigin $portalOrigin }
    if (& $backendProbe) {
        $listenerPhp = Test-BackendListenerPhp $BackendPort
        if (-not $listenerPhp.Compatible) {
            throw "The service at $backendOrigin exposes the Think Tank API contract, but its listener runtime is not safe to reuse. $($listenerPhp.Detail) No process was stopped."
        }
        Write-Host "Reusing verified Laravel backend at $backendOrigin."
    } elseif (Test-TcpListener '127.0.0.1' $BackendPort) {
        throw "Port $BackendPort is occupied but does not expose the expected Think Tank API contract for $portalOrigin. It may be an unrelated or unhealthy service. No process was stopped."
    } else {
        $resolvedPhpExecutable = Resolve-PhpExecutable $PhpExecutable
        $allowedOrigins = Join-EnvironmentList ([Environment]::GetEnvironmentVariable('THINK_TANK_PORTAL_ALLOWED_ORIGINS', 'Process')) @(
            $portalOrigin,
            "http://127.0.0.1:$PortalPort"
        )
        $statefulDomains = Join-EnvironmentList ([Environment]::GetEnvironmentVariable('SANCTUM_STATEFUL_DOMAINS', 'Process')) @(
            'localhost',
            "localhost:$PortalPort",
            '127.0.0.1',
            "127.0.0.1:$PortalPort",
            "127.0.0.1:$BackendPort",
            '::1'
        )
        $backendEnvironment = @{
            APP_URL = $backendOrigin
            THINK_TANK_PORTAL_URL = $portalOrigin
            THINK_TANK_PORTAL_ALLOWED_ORIGINS = $allowedOrigins
            SANCTUM_STATEFUL_DOMAINS = $statefulDomains
        }

        $startedBackendProcess = Invoke-WithProcessEnvironment $backendEnvironment {
            Start-Process -FilePath $resolvedPhpExecutable -ArgumentList @(
                'artisan', 'serve', '--host=127.0.0.1', "--port=$BackendPort", '--tries=1', '--no-reload'
            ) -WorkingDirectory $backendRoot -WindowStyle Hidden `
                -RedirectStandardOutput $backendStandardOutput `
                -RedirectStandardError $backendStandardError -PassThru
        }

        Wait-ForIdentity $backendProbe "Laravel backend at $backendOrigin" @(
            $backendStandardOutput,
            $backendStandardError
        )
        Write-Host "Started and verified Laravel backend at $backendOrigin."
    }

    $portalProbe = { Test-PortalIdentity $portalOrigin }
    if (& $portalProbe) {
        Write-Host "Reusing verified Think Tank portal at $portalOrigin."
    } elseif (Test-TcpListener 'localhost' $PortalPort) {
        throw "Port $PortalPort is occupied but does not expose the expected Think Tank portal and proxied session contract. It may be an unrelated, unhealthy, or differently configured service. No process was stopped."
    } else {
        $nodeExecutable = Resolve-NodeExecutable
        $startedPortalProcess = Invoke-WithProcessEnvironment @{ LARAVEL_API_BASE_URL = $backendOrigin } {
            Start-Process -FilePath $nodeExecutable -ArgumentList @(
                'node_modules/next/dist/bin/next', 'dev', '--hostname', 'localhost', '--port', "$PortalPort"
            ) -WorkingDirectory $portalRoot -WindowStyle Hidden `
                -RedirectStandardOutput $portalStandardOutput `
                -RedirectStandardError $portalStandardError -PassThru
        }

        Wait-ForIdentity $portalProbe "Think Tank portal at $portalOrigin" @(
            $portalStandardOutput,
            $portalStandardError
        )
        Write-Host "Started and verified Think Tank portal at $portalOrigin."
    }

    $launchCompleted = $true
    Write-Host "Laravel: $backendOrigin"
    Write-Host "Think Tank portal: $portalOrigin/login"
    Write-Host 'Verified the login page and the Laravel session API through the portal.'
    Write-Host 'Existing account status and email settings are preserved.'
} finally {
    if (-not $launchCompleted) {
        Stop-OwnedProcessTree $startedPortalProcess "Think Tank portal at $portalOrigin"
        Stop-OwnedProcessTree $startedBackendProcess "Laravel backend at $backendOrigin"
    }
    if ($lockTaken) {
        $launcherMutex.ReleaseMutex()
    }
    $launcherMutex.Dispose()
}
