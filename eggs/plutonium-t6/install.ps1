# Plutonium T6 — install script (Windows Wings / PowerShell)
# Uses plutonium-updater: https://github.com/mxve/plutonium-updater.rs
$ErrorActionPreference = 'Stop'

$ServerDir = if ($env:SERVER_DIR) { $env:SERVER_DIR } else { 'C:\mnt\server' }
$ConfigUrl = if ($env:T6_CONFIG_URL) { $env:T6_CONFIG_URL } else { 'https://github.com/xerxes-at/T6ServerConfigs/archive/refs/heads/master.zip' }
$UpdaterUrl = if ($env:PLUTONIUM_UPDATER_URL) {
    $env:PLUTONIUM_UPDATER_URL
} else {
    'https://github.com/mxve/plutonium-updater.rs/releases/download/v0.4.5/plutonium-updater-x86_64-pc-windows-msvc.zip'
}

$WorkDir = Join-Path $env:TEMP ("ptero-t6-install-" + [guid]::NewGuid().ToString())
$UpdaterZip = Join-Path $WorkDir 'plutonium-updater.zip'
$UpdaterDir = Join-Path $WorkDir 'updater'
$ConfigZip = Join-Path $WorkDir 'T6ServerConfigs.zip'
$ConfigExtractDir = Join-Path $WorkDir 'config-extract'
$Bootstrapper = Join-Path $ServerDir 'bin\plutonium-bootstrapper-win32.exe'
$StorageT6 = Join-Path $ServerDir 'storage\t6'

function Invoke-Robocopy {
    param(
        [string]$Source,
        [string]$Destination
    )

    robocopy $Source $Destination /E /COPY:DAT /DCOPY:DAT /R:2 /W:2 /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Host
    if ($LASTEXITCODE -ge 8) {
        throw "Robocopy failed (exit code $LASTEXITCODE) from $Source to $Destination"
    }
}

Write-Host '[1/8] Cleaning server directory contents:' $ServerDir
Set-Location $env:TEMP

if (Test-Path -LiteralPath $ServerDir) {
    Get-ChildItem -LiteralPath $ServerDir -Force | Where-Object {
        $_.Name -notlike '.wings-install*'
    } | ForEach-Object {
        Remove-Item -LiteralPath $_.FullName -Recurse -Force -ErrorAction Stop
    }
} else {
    New-Item -ItemType Directory -Path $ServerDir -Force | Out-Null
}

Set-Location $ServerDir
New-Item -ItemType Directory -Path $WorkDir -Force | Out-Null

Write-Host '[2/8] Downloading plutonium-updater'
Invoke-WebRequest -Uri $UpdaterUrl -OutFile $UpdaterZip

Write-Host '[3/8] Extracting plutonium-updater'
New-Item -ItemType Directory -Path $UpdaterDir -Force | Out-Null
Expand-Archive -LiteralPath $UpdaterZip -DestinationPath $UpdaterDir -Force

$UpdaterExe = Get-ChildItem -Path $UpdaterDir -Filter 'plutonium-updater.exe' -Recurse -File |
    Select-Object -First 1
if (-not $UpdaterExe) {
    throw "plutonium-updater.exe not found in $UpdaterDir"
}

Write-Host '[4/8] Downloading Plutonium files from CDN'
# Layout at server root: bin/, games/, storage/, launcher/
& $UpdaterExe.FullName -d $ServerDir -l -q --no-color
if ($LASTEXITCODE -ne 0) {
    throw "plutonium-updater failed (exit code $LASTEXITCODE)"
}

if (-not (Test-Path -LiteralPath $Bootstrapper)) {
    throw "Bootstrapper not found after update: $Bootstrapper"
}

Write-Host '[5/8] Downloading T6ServerConfigs'
Invoke-WebRequest -Uri $ConfigUrl -OutFile $ConfigZip

Write-Host '[6/8] Extracting T6ServerConfigs'
Start-Sleep -Seconds 2
New-Item -ItemType Directory -Path $ConfigExtractDir -Force | Out-Null
Expand-Archive -LiteralPath $ConfigZip -DestinationPath $ConfigExtractDir -Force

# GitHub zip: localappdata/Plutonium/storage/t6/dedicated.cfg
# Target:       storage/t6/dedicated.cfg (server root)
$ConfigRoot = Get-ChildItem -Path $ConfigExtractDir -Directory |
    Where-Object { Test-Path (Join-Path $_.FullName 'localappdata\Plutonium\storage\t6') } |
    Select-Object -First 1

if (-not $ConfigRoot) {
    throw "T6ServerConfigs storage/t6 folder not found in $ConfigExtractDir"
}

$ConfigStorageT6 = Join-Path $ConfigRoot.FullName 'localappdata\Plutonium\storage\t6'
Write-Host "       Config source:" $ConfigStorageT6
Write-Host "       Config target:" $StorageT6

New-Item -ItemType Directory -Path $StorageT6 -Force | Out-Null
Invoke-Robocopy -Source $ConfigStorageT6 -Destination $StorageT6

Write-Host '[7/8] Removing .ps1 and .json from server root'
Get-ChildItem -LiteralPath $ServerDir -File -Force |
    Where-Object { $_.Extension -in @('.ps1', '.json') } |
    ForEach-Object {
        Remove-Item -LiteralPath $_.FullName -Force
        Write-Host "       Removed $($_.Name)"
    }

Write-Host '[8/8] Removing temporary files'
Remove-Item $WorkDir -Recurse -Force -ErrorAction SilentlyContinue

Write-Host '[DONE] Plutonium T6 installation completed successfully'
Write-Host '       Game path :' $ServerDir
Write-Host '       Games     :' (Join-Path $ServerDir 'games')
Write-Host '       Bootstrap :' $Bootstrapper
Write-Host '       Configs   :' $StorageT6
