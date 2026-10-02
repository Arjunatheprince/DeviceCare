# ─────────────────────────────────────────────────────────────────────────────
#  DeviceCare — Agent Runner
#  Menjalankan agent Python secara terus-menerus dan mengirim data ke backend.
#  Usage: .\start-agent.ps1
#         .\start-agent.ps1 -Interval 3   (kirim tiap 3 detik, default: 1)
#         .\start-agent.ps1 -Url http://localhost:8000
# ─────────────────────────────────────────────────────────────────────────────
param(
    [int]   $Interval = 1,
    [string]$Url      = "http://localhost:8000"
)

$env:PYTHONIOENCODING = "utf-8"

$AgentDir = Join-Path $PSScriptRoot "agent"
$MainPy   = Join-Path $AgentDir "main.py"

Write-Host ""
Write-Host "  ██████  DeviceCare Agent" -ForegroundColor Cyan
Write-Host "  Interval  : ${Interval}s" -ForegroundColor Gray
Write-Host "  Backend   : $Url" -ForegroundColor Gray
Write-Host "  Tekan Ctrl+C untuk menghentikan." -ForegroundColor Gray
Write-Host ""

Set-Location $AgentDir
python main.py --loop --send --interval $Interval --url $Url
