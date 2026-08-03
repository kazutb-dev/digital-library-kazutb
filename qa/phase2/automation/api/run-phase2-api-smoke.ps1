Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$logPath = Join-Path $scriptDir '..\..\evidence\logs\phase2-api-tests.log'
$reportPath = Join-Path $scriptDir '..\..\reports\test-results\phase2-api-results.json'

$cases = @(
    'auth-access.api.test.ps1',
    'catalog-public.api.test.ps1',
    'integration-boundary.api.test.ps1',
    'admin-circulation.api.test.ps1'
)

$all = @()
$runStart = Get-Date

"=== Phase 2 API Smoke Run ($(Get-Date -Format s)) ===" | Out-File -FilePath $logPath -Encoding utf8

foreach ($case in $cases) {
    $path = Join-Path $scriptDir $case
    $t0 = Get-Date
    $json = & pwsh -NoLogo -NoProfile -File $path
    $elapsed = [math]::Round(((Get-Date) - $t0).TotalMilliseconds, 2)
    "[$case] elapsed_ms=$elapsed" | Out-File -FilePath $logPath -Append -Encoding utf8

    $parsed = $json | ConvertFrom-Json
    foreach ($row in $parsed) {
        $all += $row
        "  - $($row.testCaseId) status=$($row.statusCode) passed=$($row.passed) elapsed_ms=$($row.elapsedMs)" | Out-File -FilePath $logPath -Append -Encoding utf8
    }
}

$totalElapsed = [math]::Round(((Get-Date) - $runStart).TotalSeconds, 3)
$passCount = ($all | Where-Object { $_.passed -eq $true } | Measure-Object).Count
$failCount = ($all | Where-Object { $_.passed -ne $true } | Measure-Object).Count

"summary total_cases=$($all.Count) passed=$passCount failed=$failCount total_seconds=$totalElapsed" | Out-File -FilePath $logPath -Append -Encoding utf8

$all | ConvertTo-Json -Depth 6 | Out-File -FilePath $reportPath -Encoding utf8

if ($failCount -gt 0) {
    exit 1
}
