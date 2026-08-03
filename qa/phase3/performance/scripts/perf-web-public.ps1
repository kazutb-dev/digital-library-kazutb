<#
.SYNOPSIS
    Phase 3 Part 1 — Performance Script: External Resources & Web Catalog
    Target Module: External Resources API + Web Catalog UI (Midterm R2/R5)
    Scenarios: S05-NL-EXTRES, S06-NL-WEBCATALOG, S07-PL-WEBCATALOG

.DESCRIPTION
    Bounded sequential load test against the external resources API and web catalog HTML endpoint.
    Tooling: Native PowerShell HTTP (Invoke-WebRequest). No external tools required.
    Execution type: BOUNDED SYNTHETIC — sequential single-VU runs, not concurrent.

.PARAMETER BaseUrl
    Base URL of the application under test. Default: http://localhost

.PARAMETER ScenarioFilter
    Optional scenario ID filter. Default: run all scenarios.

.PARAMETER OutputDir
    Directory to write raw result JSON files.

.NOTES
    BOUNDED/SYNTHETIC DISCLOSURE:
    - All scenarios use 1 virtual user (sequential requests), not true concurrency.
    - Web catalog requests return full HTML page (heavier payloads than API).
    - External resources endpoint may call external services, adding latency variance.
    - Results are honest measured values under these bounded conditions.
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
$logPath = Join-Path $scriptDir '..\..\evidence\logs\perf-web-public.log'

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
        $bodyLen = if ($r.Content) { $r.Content.Length } else { 0 }
        return [PSCustomObject]@{ Success = $true; StatusCode = [int]$r.StatusCode; ElapsedMs = $ms; BodyBytes = $bodyLen; Error = '' }
    }
    catch {
        $ms = [math]::Round(((Get-Date) - $t0).TotalMilliseconds, 2)
        $code = try { [int]$_.Exception.Response.StatusCode.value__ } catch { 0 }
        return [PSCustomObject]@{ Success = $false; StatusCode = $code; ElapsedMs = $ms; BodyBytes = 0; Error = $_.Exception.Message }
    }
}

function Invoke-ScenarioRun {
    param(
        [string]$ScenarioId,
        [string]$Module,
        [string]$LoadType,
        [string]$Url,
        [int]$TotalRequests,
        [int]$ThresholdMs
    )

    if ($ScenarioFilter -and $ScenarioFilter -ne $ScenarioId) { return $null }

    Write-Log "=== Starting scenario $ScenarioId ($LoadType) | $TotalRequests requests | $Url ==="
    $samples = @()
    $runStart = Get-Date

    for ($i = 0; $i -lt $TotalRequests; $i++) {
        $res = Invoke-PerfRequest -Url $Url
        $samples += $res
        $status = if ($res.Success) { $res.StatusCode } else { "ERR($($res.StatusCode))" }
        Write-Log "  req#$($i+1) status=$status elapsed_ms=$($res.ElapsedMs) body_bytes=$($res.BodyBytes)"
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
    $avgBodyBytes = [math]::Round(($samples | ForEach-Object { $_.BodyBytes } | Measure-Object -Average).Average, 0)
    $p95Pass = $p95Ms -le $ThresholdMs
    $errPass = $errorRate -le 10
    $scenarioPassed = $p95Pass -and $errPass

    $result = [PSCustomObject]@{
        run_id            = $runId
        scenario_id       = $ScenarioId
        module            = $Module
        load_type         = $LoadType
        endpoint          = $Url
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
        avg_body_bytes    = $avgBodyBytes
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

# S05: Normal load on /api/v1/external-resources (20 requests)
$r = Invoke-ScenarioRun -ScenarioId 'S05-NL-EXTRES' -Module 'External Resources' -LoadType 'normal_load' `
    -Url "$BaseUrl/api/v1/external-resources" -TotalRequests 20 -ThresholdMs 5000
if ($r) { $allResults += $r }

# S06: Normal load on /catalog web page (10 requests)
$r = Invoke-ScenarioRun -ScenarioId 'S06-NL-WEBCATALOG' -Module 'Web Catalog UI' -LoadType 'normal_load' `
    -Url "$BaseUrl/catalog" -TotalRequests 10 -ThresholdMs 8000
if ($r) { $allResults += $r }

# S07: Peak load on /catalog web page (20 requests)
$r = Invoke-ScenarioRun -ScenarioId 'S07-PL-WEBCATALOG' -Module 'Web Catalog UI' -LoadType 'peak_load' `
    -Url "$BaseUrl/catalog" -TotalRequests 20 -ThresholdMs 10000
if ($r) { $allResults += $r }

# --- Output ---
$outFile = Join-Path $OutputDir "web-public-perf-$runId.json"
$allResults | ConvertTo-Json -Depth 6 | Out-File -FilePath $outFile -Encoding utf8

$passCount = ($allResults | Where-Object { $_.scenario_passed } | Measure-Object).Count
$failCount2 = ($allResults | Where-Object { -not $_.scenario_passed } | Measure-Object).Count
Write-Log "=== Web/Public Performance Run Complete | $($allResults.Count) scenarios | PASS=$passCount FAIL=$failCount2 | results=$outFile ==="

Write-Host "`nWeb/Public Performance Run Summary:"
$allResults | ForEach-Object {
    $status = if ($_.scenario_passed) { "[PASS]" } else { "[FAIL]" }
    Write-Host "  $status $($_.scenario_id) | avg=$($_.avg_ms)ms p95=$($_.p95_ms)ms err=$($_.error_rate_pct)% rps=$($_.throughput_rps)"
}
Write-Host "`nResults written to: $outFile"
$allResults | ConvertTo-Json -Depth 6
