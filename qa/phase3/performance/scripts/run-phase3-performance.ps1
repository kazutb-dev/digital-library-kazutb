<#
.SYNOPSIS
    Phase 3 Part 1 — Master Performance Runner
    Executes all Phase 3 Part 1 performance scenarios and aggregates results.

.DESCRIPTION
    Orchestrates all three bounded performance test scripts:
    1. perf-catalog-api.ps1    (Catalog/Public API — R2 targets)
    2. perf-web-public.ps1     (External Resources + Web Catalog UI)
    3. perf-integration-boundary.ps1 (Integration API boundary — R4+R6)

    Produces combined summary JSON and logs to evidence/logs/.

.PARAMETER BaseUrl
    Base URL of the application under test. Default: http://localhost

.NOTES
    BOUNDED/SYNTHETIC DISCLOSURE:
    All sub-scripts use bounded sequential single-VU workloads.
    See individual script headers for full disclosure.
#>
param(
    [string]$BaseUrl = 'http://localhost'
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$resultsDir = Join-Path $scriptDir '..\..\performance\results'
$logPath    = Join-Path $scriptDir '..\..\evidence\logs\phase3-perf-run.log'
$summaryPath = Join-Path $resultsDir 'phase3-perf-summary.json'
$null = New-Item -ItemType Directory -Path $resultsDir -Force

$runId = Get-Date -Format 'yyyyMMdd-HHmmss'
$runStart = Get-Date

function Write-Log {
    param([string]$Msg)
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    "[$ts] $Msg" | Tee-Object -FilePath $logPath -Append | Out-Null
    Write-Host "[$ts] $Msg"
}

Write-Log "=== Phase 3 Part 1 Performance Test Run | run_id=$runId | base_url=$BaseUrl ==="
Write-Log "=== Environment: Windows/PowerShell | PHP 8.4 | Nginx/local | BOUNDED SYNTHETIC ==="

$allScenarioResults = @()

# --- Script 1: Catalog API ---
Write-Log "--- Running perf-catalog-api.ps1 ---"
$t1 = Get-Date
$catalogRawJson = & pwsh -NoLogo -NoProfile -File "$scriptDir\perf-catalog-api.ps1" -BaseUrl $BaseUrl -OutputDir $resultsDir 2>&1
$s1Elapsed = [math]::Round(((Get-Date)-$t1).TotalSeconds,3)
try {
    $catalogResults = $catalogRawJson | Where-Object { $_ -is [string] -and $_.TrimStart().StartsWith('[') } | Select-Object -Last 1 | ConvertFrom-Json
    foreach ($r in $catalogResults) { $allScenarioResults += $r }
    Write-Log "--- perf-catalog-api.ps1 done | elapsed=${s1Elapsed}s | scenarios=$($catalogResults.Count) ---"
} catch {
    Write-Log "--- perf-catalog-api.ps1 output parse warning: $_"
}

# --- Script 2: Web/Public ---
Write-Log "--- Running perf-web-public.ps1 ---"
$t2 = Get-Date
$webRawJson = & pwsh -NoLogo -NoProfile -File "$scriptDir\perf-web-public.ps1" -BaseUrl $BaseUrl -OutputDir $resultsDir 2>&1
$s2Elapsed = [math]::Round(((Get-Date)-$t2).TotalSeconds,3)
try {
    $webResults = $webRawJson | Where-Object { $_ -is [string] -and $_.TrimStart().StartsWith('[') } | Select-Object -Last 1 | ConvertFrom-Json
    foreach ($r in $webResults) { $allScenarioResults += $r }
    Write-Log "--- perf-web-public.ps1 done | elapsed=${s2Elapsed}s | scenarios=$($webResults.Count) ---"
} catch {
    Write-Log "--- perf-web-public.ps1 output parse warning: $_"
}

# --- Script 3: Integration Boundary ---
Write-Log "--- Running perf-integration-boundary.ps1 ---"
$t3 = Get-Date
$intRawJson = & pwsh -NoLogo -NoProfile -File "$scriptDir\perf-integration-boundary.ps1" -BaseUrl $BaseUrl -OutputDir $resultsDir 2>&1
$s3Elapsed = [math]::Round(((Get-Date)-$t3).TotalSeconds,3)
try {
    $intResult = $intRawJson | Where-Object { $_ -is [string] -and $_.TrimStart().StartsWith('{') } | Select-Object -Last 1 | ConvertFrom-Json
    Write-Log "--- perf-integration-boundary.ps1 done | elapsed=${s3Elapsed}s ---"
} catch {
    Write-Log "--- perf-integration-boundary.ps1 output parse warning: $_"
    $intResult = $null
}

$totalElapsedSec = [math]::Round(((Get-Date)-$runStart).TotalSeconds,3)
$passCount = ($allScenarioResults | Where-Object { $_.scenario_passed } | Measure-Object).Count
$failCount = ($allScenarioResults | Where-Object { -not $_.scenario_passed } | Measure-Object).Count

$summary = [PSCustomObject]@{
    run_id             = $runId
    base_url           = $BaseUrl
    total_elapsed_sec  = $totalElapsedSec
    scenario_count     = $allScenarioResults.Count
    pass_count         = $passCount
    fail_count         = $failCount
    execution_type     = 'bounded_synthetic_sequential'
    environment        = 'local_nginx_php84'
    timestamp          = (Get-Date -Format 'yyyy-MM-ddTHH:mm:ssZ')
    catalog_scenarios  = $allScenarioResults | Where-Object { $_.module -like '*Catalog*' }
    web_scenarios      = $allScenarioResults | Where-Object { $_.module -like '*Web*' }
    extres_scenarios   = $allScenarioResults | Where-Object { $_.module -like '*External*' }
    integration_result = $intResult
}

$summary | ConvertTo-Json -Depth 8 | Out-File -FilePath $summaryPath -Encoding utf8
Write-Log "=== PHASE 3 PART 1 PERFORMANCE RUN COMPLETE | total_elapsed=${totalElapsedSec}s | PASS=$passCount FAIL=$failCount ==="
Write-Log "=== Summary written to: $summaryPath ==="

Write-Host "`n========================================"
Write-Host "PHASE 3 PART 1 PERFORMANCE SUMMARY"
Write-Host "========================================"
Write-Host "Run ID: $runId"
Write-Host "Total elapsed: ${totalElapsedSec}s"
foreach ($r in $allScenarioResults) {
    $s = if ($r.scenario_passed) { "PASS" } else { "FAIL" }
    Write-Host "  [$s] $($r.scenario_id) | avg=$($r.avg_ms)ms p95=$($r.p95_ms)ms err=$($r.error_rate_pct)% rps=$($r.throughput_rps)"
}
if ($intResult) {
    $s = if ($intResult.scenario_passed) { "PASS" } else { "FAIL" }
    Write-Host "  [$s] S08-BND-INTEGRATION | middleware_overhead=$($intResult.middleware_overhead_avg_ms)ms"
}
Write-Host "Summary: $summaryPath"
