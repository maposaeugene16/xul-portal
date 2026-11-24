param([switch]$NoBrowser)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$webHost = '127.0.0.1'
$webPort = 8000

function Show-PortalError([string]$message) {
    try {
        $shell = New-Object -ComObject WScript.Shell
        $null = $shell.Popup($message, 0, 'Mavambo SchoolOS', 16)
    } catch {
        Write-Error $message
    }
}

try {
    $phpPath = $env:SCHOOL_PHP
    if (-not $phpPath -or -not (Test-Path -LiteralPath $phpPath)) {
        $xamppHome = if ($env:XAMPP_HOME) { $env:XAMPP_HOME } else { 'C:\xampp' }
        $candidate = Join-Path $xamppHome 'php\php.exe'
        if (Test-Path -LiteralPath $candidate) { $phpPath = $candidate }
        else {
            $phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
            if ($phpCommand) { $phpPath = $phpCommand.Source }
        }
    }
    if (-not $phpPath -or -not (Test-Path -LiteralPath $phpPath)) {
        throw 'PHP was not found. Install XAMPP or set the SCHOOL_PHP environment variable to your php.exe path.'
    }

    $dbSettings = & $phpPath -r '$c = require "config.php"; echo $c["host"],"|",$c["port"];'
    if ($LASTEXITCODE -ne 0 -or $dbSettings -notmatch '^(.+)\|([0-9]{1,5})$') {
        throw 'The database settings could not be read from config.php.'
    }
    $dbHost = $Matches[1]
    $dbPort = [int]$Matches[2]
    if (-not (Test-NetConnection -ComputerName $dbHost -Port $dbPort -InformationLevel Quiet -WarningAction SilentlyContinue)) {
        throw "MySQL/MariaDB is not reachable at ${dbHost}:$dbPort. Start the MySQL service in the XAMPP Control Panel, then try again."
    }

    $url = "http://${webHost}:$webPort"
    $listener = Get-NetTCPConnection -LocalAddress $webHost -LocalPort $webPort -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($listener) {
        try {
            $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 3
            if ([int]$response.StatusCode -ne 200 -or $response.Content -notmatch 'Mavambo') {
                throw "Port $webPort is already serving another application. Close that application and try again."
            }
        } catch {
            if ($_.Exception.Message -like 'Port * is already serving*') { throw }
            throw "Port $webPort is already in use, but the school portal did not respond there. Close the other application and try again."
        }
    } else {
        $phpServer = Start-Process -FilePath $phpPath -ArgumentList @('-S',"${webHost}:$webPort",'-t',("`"{0}`"" -f $projectRoot)) -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru
        $ready = $false
        for ($attempt = 0; $attempt -lt 20; $attempt++) {
            Start-Sleep -Milliseconds 500
            if ($phpServer.HasExited) { throw 'PHP stopped while starting the portal. Check that port 8000 is available.' }
            try {
                $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 2
                if ([int]$response.StatusCode -eq 200 -and $response.Content -match 'Mavambo') { $ready = $true; break }
            } catch { }
        }
        if (-not $ready) {
            if (-not $phpServer.HasExited) { Stop-Process -Id $phpServer.Id -Force -ErrorAction SilentlyContinue }
            throw 'The portal did not become ready. Check the PHP and database configuration, then try again.'
        }
    }

    if (-not $NoBrowser) { Start-Process $url }
} catch {
    Show-PortalError $_.Exception.Message
    exit 1
}
