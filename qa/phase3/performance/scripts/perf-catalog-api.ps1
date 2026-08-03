<#
.SYNOPSIS
    Phase 3 Part 1 — Performance Script: Catalog/Public API
    Target Module: Catalog/Public API (R2, Midterm Risk Score: 20)
    Scenarios: S01-NL-CATALOG, S02-PL-CATALOG, S03-NL-SUBJECTS, S04-SL-MIXED, S09-END-CATALOG

.DESCRIPTION
    Bounded sequential load test against the KazUTB Digital Library public catalog API.
    Tooling: Native PowerShell HTTP (Invoke-WebRequest). No external tools required.
    Execution type: BOUNDED SYNTHETIC — sequential single-VU runs, not concurrent.
    All requests are read-only GET operations against unauthenticated public endpoints.

.PARAMETER BaseUrl
    Base URL of the application under test. Default: http://localhost

.PARAMETER ScenarioFilter
    Optional scenario ID filter (e.g., 'S01-NL-CATALOG'). Default: run all scenarios.

.PARAMETER OutputDir
    Directory to write raw result JSON files. Default: qa/phase3/performance/results/

.NOTES
    BOUNDED/SYNTHETIC DISCLOSURE:
    - All scenarios use 1 virtual user (sequential requests), not true concurrency.
    - Request counts and durations are reduced from production-scale to safe bounded levels.
    - Results are honest measured values under these bounded conditions.
    - Production-scale concurrency would require dedicated load infrastructure.
#>
param(
    [string]$BaseUrl = 'http://localhost',
    [string]$ScenarioFilter = '',
    [string]$OutputDir = ''
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not $OutputDir) {
    $OutputDir = Join-Path $scriptDir '..\..\performance\results'
}
$null = New-Item -ItemType Directory -Path $OutputDir -Force

$runId = Get-Date -Format 'yyyyMMdd-HHmmss'
$logPath = Join-Path $scriptDir '..\..\evidence\logs\perf-catalog-api.log'

function Write-Log {
    param([string]$Msg)
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    "[$ts] $Msg" | Tee-Object -FilePath $logPath -Append | Out-Null
}

function Invoke-PerfRequest {
    param([string]$Url, [int]$TimeoutSec = 30)
    $t0 = Get-Date
    try {
        $r = Invoke-WebRequest -Uri $Url -Method GET -TimeoutSec $TimeoutSec -UseBasicParsing
        $ms = [math]::Round(((Get-Date) - $t0).TotalMilliseconds, 2)
        return [PSCustomObject]@{ Success = $true; StatusCode = [int]$r.StatusCode; ElapsedMs = $ms; Error = '' }
    }
    catch {
        $ms = [math]::Round(((Get-Date) - $t0).TotalMilliseconds, 2)
        $code = try { [int]$_.Exception.Response.StatusCode.value__ } catch { 0 }
        return [PSCustomObject]@{ Success = $false; StatusCode = $code; ElapsedMs = $ms; Error = $_.Exception.Message }
    }
}

function Run-Scenario {
    param(
        [string]$ScenarioId,
        [string]$Module,
        [string]$LoadType,
        [string[]]$Urls,
        [int]$TotalRequests,
        [string]$Pattern,
        [int]$ThresholdMs
    )

    if ($ScenarioFilter -and $ScenarioFilter -ne $ScenarioId) { return $null }

    Write-Log "=== Starting scenario $ScenarioId ($LoadType) | $TotalRequests requests ==="
    $samples = @()
    $urlCount = $Urls.Count
    $runStart = Get-Date

    for ($i = 0; $i -lt $TotalRequests; $i++) {
        # Alternating pattern for spike scenarios
        $url = $Urls[$i % $urlCount]
        $res = Invoke-PerfRequest -Url $url
        $samples += $res
        $status = if ($res.Success) { $res.StatusCode } else { "ERR($($res.StatusCode))" }
        Write-Log "  req#$($i+1) url=$url status=$status elapsed_ms=$($res.ElapsedMs)"
    }

    $totalElapsedSec = [math]::Round(((Get-Date) - $runStart).TotalSeconds, 3)
    $allMs = $samples | ForEach-Object { $_.ElapsedMs }
    $sortedMs = $allMs | Sort-Object
    $count = $sortedMs.Count
    $avgMs = [math]::Round(($allMs | Measure-Object -Average).Average, 2)
    $medianMs = if ($count % 2 -eq 0) {
        [math]::Round(($sortedMs[$count / 2 - 1] + $sortedMs[$count / 2]) / 2, 2)
    }
    else {
        [math]::Round($sortedMs[[int]($count / 2)], 2)
    }
    $p95Index = [math]::Ceiling(0.95 * $count) - 1
    $p95Ms = [math]::Round($sortedMs[$p95Index], 2)
    $minMs = [math]::Round(($allMs | Measure-Object -Minimum).Minimum, 2)
    $maxMs = [math]::Round(($allMs | Measure-Object -Maximum).Maximum, 2)
    $successCount = ($samples | Where-Object { $_.Success } | Measure-Object).Count
    $failCount = $TotalRequests - $successCount
    $errorRate = [math]::Round(($failCount / $TotalRequests) * 100, 2)
    $throughput = if ($totalElapsedSec -gt 0) { [math]::Round($TotalRequests / $totalElapsedSec, 3) } else { 0 }
    $p95Pass = $p95Ms -le $ThresholdMs
    $errPass = $errorRate -le 5
    $scenarioPassed = $p95Pass -and $errPass

    $result = [PSCustomObject]@{
        run_id            = $runId
        scenario_id       = $ScenarioId
        module            = $Module
        load_type         = $LoadType
        total_requests    = $TotalRequests
        success_count     = $successCount
        fail_count        = $failCount
        error_rate_pct    = $errorRate
        avg_ms            = $avgMs
        median_ms         = $medianMs
        p95_ms            = $p95Ms
        min_ms            = $minMs
        max_ms            = $maxMs
        throughput_rps    = $throughput
        total_elapsed_sec = $totalElapsedSec
        threshold_ms      = $ThresholdMs
        p95_pass          = $p95Pass
        error_rate_pass   = $errPass
        scenario_passed   = $scenarioPassed
        dataset_type      = 'bounded_synthetic'
        environment       = 'local_nginx_php84'
        timestamp         = (Get-Date -Format 'yyyy-MM-ddTHH:mm:ssZ')
    }

    $label = if ($scenarioPassed) { "PASS" } else { "FAIL" }
    Write-Log "=== $ScenarioId RESULT: $label | avg=${avgMs}ms p95=${p95Ms}ms err=${errorRate}% rps=$throughput ==="
    return $result
}

# --- Scenario Definitions ---
$allResults = @()

# S01: Normal load on /api/v1/landing (20 requests)
$r = Run-Scenario -ScenarioId 'S01-NL-CATALOG' -Module 'Catalog/Public API' -LoadType 'normal_load' `
    -Urls @("$BaseUrl/api/v1/landing") -TotalRequests 20 -Pattern 'sequential' -ThresholdMs 5000
if ($r) { $allResults += $r }

# S02: Peak load on /api/v1/catalog-db (30 requests)
$r = Run-Scenario -ScenarioId 'S02-PL-CATALOG' -Module 'Catalog/Public API' -LoadType 'peak_load' `
    -Urls @("$BaseUrl/api/v1/catalog-db?limit=10") -TotalRequests 30 -Pattern 'sequential' -ThresholdMs 6000
if ($r) { $allResults += $r }

# S03: Normal load on /api/v1/subjects (20 requests)
$r = Run-Scenario -ScenarioId 'S03-NL-SUBJECTS' -Module 'Catalog/Public API' -LoadType 'normal_load' `
    -Urls @("$BaseUrl/api/v1/subjects") -TotalRequests 20 -Pattern 'sequential' -ThresholdMs 5000
if ($r) { $allResults += $r }

# S04: Spike mixed (alternating landing + subjects, 20 total)
$r = Run-Scenario -ScenarioId 'S04-SL-MIXED' -Module 'Catalog/Public API' -LoadType 'spike_load' `
    -Urls @("$BaseUrl/api/v1/landing", "$BaseUrl/api/v1/subjects") -TotalRequests 20 -Pattern 'alternating' -ThresholdMs 6000
if ($r) { $allResults += $r }

# S09: Endurance on /api/v1/landing (40 requests)
$r = Run-Scenario -ScenarioId 'S09-END-CATALOG' -Module 'Catalog/Public API' -LoadType 'endurance' `
    -Urls @("$BaseUrl/api/v1/landing") -TotalRequests 40 -Pattern 'sequential' -ThresholdMs 6000
if ($r) { $allResults += $r }

# --- Output ---
$outFile = Join-Path $OutputDir "catalog-api-perf-$runId.json"
$allResults | ConvertTo-Json -Depth 6 | Out-File -FilePath $outFile -Encoding utf8

$passCount = ($allResults | Where-Object { $_.scenario_passed } | Measure-Object).Count
$failCount2 = ($allResults | Where-Object { -not $_.scenario_passed } | Measure-Object).Count
Write-Log "=== Catalog API Performance Run Complete | $($allResults.Count) scenarios | PASS=$passCount FAIL=$failCount2 | results=$outFile ==="

Write-Host "`nCatalog API Performance Run Summary:"
$allResults | ForEach-Object {
    $status = if ($_.scenario_passed) { "[PASS]" } else { "[FAIL]" }
    Write-Host "  $status $($_.scenario_id) | avg=$($_.avg_ms)ms p95=$($_.p95_ms)ms err=$($_.error_rate_pct)% rps=$($_.throughput_rps)"
}
Write-Host "`nResults written to: $outFile"
$allResults | ConvertTo-Json -Depth 6
