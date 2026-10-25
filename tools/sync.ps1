# Houdt je lokale kopie automatisch up-to-date met GitHub (Windows PowerShell).
# Gebruik: .\tools\sync.ps1 [-Interval 60]    Stoppen: Ctrl+C
param([int]$Interval = 60)

Set-Location (Join-Path $PSScriptRoot "..")
Write-Host "Sync gestart in $(Get-Location), elke $Interval s. Stoppen met Ctrl+C."

while ($true) {
    $out = git pull --ff-only 2>&1 | Out-String
    if ($LASTEXITCODE -ne 0) {
        Write-Host "$(Get-Date -Format HH:mm:ss) Pull mislukt (lokale wijzigingen?): $out"
    } elseif ($out -notmatch "Already up to date") {
        Write-Host "$(Get-Date -Format HH:mm:ss) Bijgewerkt:`n$out"
    }
    Start-Sleep -Seconds $Interval
}
