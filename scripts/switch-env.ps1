param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('local', 'production')]
    [string]$Profile
)

$ErrorActionPreference = 'Stop'

$configDir = Join-Path $PSScriptRoot '..\config'
$sourceFile = Join-Path $configDir ("env.$Profile.php")
$targetFile = Join-Path $configDir 'env.php'

if (-not (Test-Path $sourceFile)) {
    Write-Error "Profile file not found: $sourceFile"
}

Copy-Item -Path $sourceFile -Destination $targetFile -Force
Write-Host "Active profile switched to: $Profile"
Write-Host "Copied: $sourceFile"
Write-Host "To:     $targetFile"
