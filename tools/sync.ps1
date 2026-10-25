# Houdt je lokale kopie automatisch up-to-date met de testbranch op GitHub (Windows PowerShell).
# Gebruik: .\tools\sync.ps1 [-Interval 60] [-Branch dev]    Stoppen: Ctrl+C
param([int]$Interval = 60, [string]$Branch = "dev")

Set-Location (Join-Path $PSScriptRoot "..")

git fetch origin $Branch -q
if ($LASTEXITCODE -ne 0) { Write-Host "Kan branch '$Branch' niet ophalen."; exit 1 }
git checkout $Branch -q 2>$null
if ($LASTEXITCODE -ne 0) { git checkout -b $Branch "origin/$Branch" -q }

Write-Host "Sync gestart op branch '$Branch' in $(Get-Location), elke $Interval s. Stoppen met Ctrl+C."

while ($true) {
    $out = git pull --ff-only origin $Branch 2>&1 | Out-String
    if ($LASTEXITCODE -ne 0) {
        Write-Host "$(Get-Date -Format HH:mm:ss) Pull mislukt (lokale wijzigingen?): $out"
    } elseif ($out -notmatch "Already up to date") {
        Write-Host "$(Get-Date -Format HH:mm:ss) Bijgewerkt:`n$out"
    }
    Start-Sleep -Seconds $Interval
}
