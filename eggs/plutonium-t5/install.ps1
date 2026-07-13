# Plutonium T5 — install script (Windows Wings / PowerShell)
# Uses plutonium-updater: https://github.com/mxve/plutonium-updater.rs
$ErrorActionPreference = 'Stop'

$ServerDir = if ($env:SERVER_DIR) { $env:SERVER_DIR } else { 'C:\mnt\server' }
$ConfigUrl = if ($env:T5_CONFIG_URL) { $env:T5_CONFIG_URL } else { 'https://github.com/xerxes-at/T5ServerConfig/archive/refs/heads/master.zip' }
$UpdaterUrl = if ($env:PLUTONIUM_UPDATER_URL) {
    $env:PLUTONIUM_UPDATER_URL
} else {
    'https://github.com/mxve/plutonium-updater.rs/releases/download/v0.4.5/plutonium-updater-x86_64-pc-windows-msvc.zip'
}

$WorkDir = Join-Path $env:TEMP ("ptero-t5-install-" + [guid]::NewGuid().ToString())
$UpdaterZip = Join-Path $WorkDir 'plutonium-updater.zip'
$UpdaterDir = Join-Path $WorkDir 'updater'
$ConfigZip = Join-Path $WorkDir 'T5ServerConfig.zip'
$ConfigExtractDir = Join-Path $WorkDir 'config-extract'
$ServerLocalAppData = Join-Path $ServerDir 'localappdata'
$Bootstrapper = Join-Path $ServerLocalAppData 'Plutonium\bin\plutonium-bootstrapper-win32.exe'

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

Write-Host '[1/8] Removing server directory:' $ServerDir
Set-Location $env:TEMP
if (Test-Path -LiteralPath $ServerDir) {
    Remove-Item -LiteralPath $ServerDir -Recurse -Force
}

Write-Host '[2/8] Creating clean server directory'
New-Item -ItemType Directory -Path $ServerDir -Force | Out-Null
New-Item -ItemType Directory -Path $ServerLocalAppData -Force | Out-Null
New-Item -ItemType Directory -Path $WorkDir -Force | Out-Null

Write-Host '[3/8] Downloading plutonium-updater'
Invoke-WebRequest -Uri $UpdaterUrl -OutFile $UpdaterZip

Write-Host '[4/8] Extracting plutonium-updater'
New-Item -ItemType Directory -Path $UpdaterDir -Force | Out-Null
Expand-Archive -LiteralPath $UpdaterZip -DestinationPath $UpdaterDir -Force

$UpdaterExe = Get-ChildItem -Path $UpdaterDir -Filter 'plutonium-updater.exe' -Recurse -File |
    Select-Object -First 1
if (-not $UpdaterExe) {
    throw "plutonium-updater.exe not found in $UpdaterDir"
}

Write-Host '[5/8] Downloading Plutonium T5 files from CDN'
$env:LOCALAPPDATA = $ServerLocalAppData
& $UpdaterExe.FullName -d $ServerDir -l -q --no-color --threads 4
if ($LASTEXITCODE -ne 0) {
    throw "plutonium-updater failed (exit code $LASTEXITCODE)"
}

if (-not (Test-Path -LiteralPath $Bootstrapper)) {
    throw "Bootstrapper not found after update: $Bootstrapper"
}

Write-Host '[6/8] Downloading T5ServerConfig'
Invoke-WebRequest -Uri $ConfigUrl -OutFile $ConfigZip

Write-Host '[7/8] Extracting T5ServerConfig'
Start-Sleep -Seconds 2
New-Item -ItemType Directory -Path $ConfigExtractDir -Force | Out-Null
Expand-Archive -LiteralPath $ConfigZip -DestinationPath $ConfigExtractDir -Force

# GitHub zip layout:
#   T5ServerConfig-master/
#     !start_mp_server.bat
#     !start_zm_server.bat
#     localappdata/Plutonium/storage/t5/dedicated.cfg
$ConfigRoot = Get-ChildItem -Path $ConfigExtractDir -Directory |
    Where-Object { Test-Path (Join-Path $_.FullName 'localappdata') } |
    Select-Object -First 1

if (-not $ConfigRoot) {
    throw "T5ServerConfig root folder not found (expected T5ServerConfig-*/localappdata in $ConfigExtractDir)"
}

Write-Host "       Config root:" $ConfigRoot.FullName

$ConfigLocalAppData = Join-Path $ConfigRoot.FullName 'localappdata'
if (-not (Test-Path -LiteralPath $ConfigLocalAppData -PathType Container)) {
    throw "T5ServerConfig localappdata folder not found: $ConfigLocalAppData"
}

Write-Host "       Config source:" $ConfigLocalAppData
Write-Host "       Config target:" $ServerLocalAppData

# Merges localappdata/Plutonium/storage/t5 into server localappdata (keeps bootstrapper from updater)
Invoke-Robocopy -Source $ConfigLocalAppData -Destination $ServerLocalAppData

Write-Host '[8/8] Removing temporary files'
Remove-Item $WorkDir -Recurse -Force -ErrorAction SilentlyContinue

Write-Host '[DONE] Plutonium T5 installation completed successfully'
Write-Host '       Game path :' $ServerDir
Write-Host '       Bootstrap :' $Bootstrapper
Write-Host '       Configs   :' (Join-Path $ServerLocalAppData 'Plutonium\storage\t5')
