<#
.SYNOPSIS
    Phase 3 Part 1 — Performance Script: Integration API Boundary
    Target Module: Integration API (R4+R6, Midterm Risk Score: 20)
    Scenarios: S08-BND-INTEGRATION

.DESCRIPTION
    Bounded sequential test against the Integration API boundary endpoint.
    Measures middleware response latency and documents auth rejection behavior.
    Tooling: Native PowerShell HTTP (Invoke-WebRequest). No external tools required.
    Execution type: BOUNDED SYNTHETIC — sequential single-VU runs, not concurrent.

.PARAMETER BaseUrl
    Base URL of the application under test. Default: http://localhost

.PARAMETER OutputDir
    Directory to write raw result JSON files.

.NOTES
    BOUNDED/SYNTHETIC DISCLOSURE:
    - All scenarios use 1 virtual user (sequential requests), not true concurrency.
    - The integration boundary rejects unauthenticated or invalid-token requests.
    - Expected HTTP responses: 401 (no headers) or 400 (invalid bearer token).
    - This script measures the latency cost of the authentication middleware rejection,
      which is a valid performance signal for middleware overhead analysis.
    - A valid production integration token is not available in the test environment.
    - Results document the auth rejection pathway latency, not successful throughput.
#>
param(
    [string]$BaseUrl = 'http://localhost',
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
$logPath = Join-Path $scriptDir '..\..\evidence\logs\perf-integration-boundary.log'

function Write-Log {
    param([string]$Msg)
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    "[$ts] $Msg" | Tee-Object -FilePath $logPath -Append | Out-Null
}

function Invoke-BoundaryRequest {
    param([string]$Url, [hashtable]$Headers = @{}, [int]$TimeoutSec = 20)
    $t0 = Get-Date
    try {
        $params = @{
            Uri = $Url; Method = 'GET'; TimeoutSec = $TimeoutSec; UseBasicParsing = $true
        }
        if ($Headers.Count -gt 0) { $params.Headers = $Headers }
        $r = Invoke-WebRequest @params
        $ms = [math]::Round(((Get-Date) - $t0).TotalMilliseconds, 2)
        return [PSCustomObject]@{ StatusCode = [int]$r.StatusCode; ElapsedMs = $ms; IsAuthError = $false; Error = '' }
    }
    catch {
        $ms = [math]::Round(((Get-Date) - $t0).TotalMilliseconds, 2)
        $code = try { [int]$_.Exception.Response.StatusCode.value__ } catch { 0 }
        $isAuth = $code -eq 401 -or $code -eq 400 -or $code -eq 403
        return [PSCustomObject]@{ StatusCode = $code; ElapsedMs = $ms; IsAuthError = $isAuth; Error = $_.Exception.Message }
    }
}

function Compute-Percentile {
    param([double[]]$Sorted, [double]$Pct)
    $idx = [math]::Ceiling($Pct * $Sorted.Count) - 1
    if ($idx -lt 0) { $idx = 0 }
    return [math]::Round($Sorted[$idx], 2)
}

Write-Log "=== Starting Integration Boundary Performance Test | run_id=$runId ==="

# --- Sub-scenario A: No-auth requests (expect 401) ---
$TotalA = 10
$samplesA = @()
Write-Log "-- Sub-scenario A: No-auth requests (expect 401) | $TotalA requests --"
for ($i = 0; $i -lt $TotalA; $i++) {
    $res = Invoke-BoundaryRequest -Url "$BaseUrl/api/integration/v1/_boundary/ping"
    $samplesA += $res
    Write-Log "  req#$($i+1) status=$($res.StatusCode) elapsed_ms=$($res.ElapsedMs) auth_error=$($res.IsAuthError)"
}

# --- Sub-scenario B: Invalid-token requests (expect 400/401) ---
$TotalB = 10
$samplesB = @()
$testHeaders = @{
    'X-Institution-Code' = 'KAZUTB-PERF-TEST'
    'X-System-Id'        = 'phase3-perf-script'
    'X-Request-Id'       = '00000000'
    'X-Api-Version'      = 'v1'
    'X-Timestamp'        = (Get-Date -Format "yyyy-MM-ddTHH:mm:ssZ")
    'Authorization'      = 'Bearer invalid-test-token-phase3'
}
Write-Log "-- Sub-scenario B: Invalid-token requests (expect 400/401) | $TotalB requests --"
for ($i = 0; $i -lt $TotalB; $i++) {
    $testHeaders['X-Request-Id'] = "{0:D8}" -f $i
    $testHeaders['X-Timestamp'] = (Get-Date -Format "yyyy-MM-ddTHH:mm:ssZ")
    $res = Invoke-BoundaryRequest -Url "$BaseUrl/api/integration/v1/_boundary/ping" -Headers $testHeaders
    $samplesB += $res
    Write-Log "  req#$($i+1) status=$($res.StatusCode) elapsed_ms=$($res.ElapsedMs) auth_error=$($res.IsAuthError)"
}

# --- Statistics helper ---
function Get-Stats {
    param($Samples, [string]$SubScenarioId, [string]$Label)
    $allMs = $Samples | ForEach-Object { $_.ElapsedMs }
    $sortedMs = [double[]]($allMs | Sort-Object)
    $count = $sortedMs.Count
    $avg = [math]::Round(($allMs | Measure-Object -Average).Average, 2)
    $med = if ($count % 2 -eq 0) { ($sortedMs[$count / 2 - 1] + $sortedMs[$count / 2]) / 2 } else { $sortedMs[[int]($count / 2)] }
    $med = [math]::Round($med, 2)
    $p95 = Compute-Percentile -Sorted $sortedMs -Pct 0.95
    $min = [math]::Round(($allMs | Measure-Object -Minimum).Minimum, 2)
    $max = [math]::Round(($allMs | Measure-Object -Maximum).Maximum, 2)
    $authErrors = ($Samples | Where-Object { $_.IsAuthError } | Measure-Object).Count
    return [PSCustomObject]@{
        sub_scenario_id  = $SubScenarioId
        label            = $Label
        total_requests   = $count
        auth_error_count = $authErrors
        avg_ms           = $avg
        median_ms        = $med
        p95_ms           = $p95
        min_ms           = $min
        max_ms           = $max
    }
}

$statsA = Get-Stats -Samples $samplesA -SubScenarioId 'S08A-NO-AUTH' -Label 'No-auth (expect 401)'
$statsB = Get-Stats -Samples $samplesB -SubScenarioId 'S08B-BAD-TOKEN' -Label 'Invalid-token (expect 400/401)'

# Middleware overhead: average time to reject unauthorized request
$middlewareOverheadMs = [math]::Round(($statsA.avg_ms + $statsB.avg_ms) / 2, 2)
$thresholdMs = 2000
$scenarioPassed = $statsA.p95_ms -le $thresholdMs -and $statsB.p95_ms -le $thresholdMs

$result = [PSCustomObject]@{
    run_id                     = $runId
    scenario_id                = 'S08-BND-INTEGRATION'
    module                     = 'Integration API'
    component                  = 'BoundaryMiddleware'
    load_type                  = 'boundary_auth'
    endpoint                   = "$BaseUrl/api/integration/v1/_boundary/ping"
    dataset_type               = 'bounded_synthetic'
    environment                = 'local_nginx_php84'
    auth_mode_a                = 'no_auth_headers'
    auth_mode_b                = 'invalid_bearer_token'
    sub_scenario_a             = $statsA
    sub_scenario_b             = $statsB
    middleware_overhead_avg_ms = $middlewareOverheadMs
    threshold_ms               = $thresholdMs
    scenario_passed            = $scenarioPassed
    note                       = 'Integration API rejects test requests; measuring auth rejection latency as middleware overhead metric'
    timestamp                  = (Get-Date -Format 'yyyy-MM-ddTHH:mm:ssZ')
}

$label = if ($scenarioPassed) { "PASS" } else { "FAIL" }
Write-Log "=== S08-BND-INTEGRATION RESULT: $label | middleware_overhead_avg=${middlewareOverheadMs}ms | p95_noauth=$($statsA.p95_ms)ms p95_badtoken=$($statsB.p95_ms)ms ==="

$outFile = Join-Path $OutputDir "integration-boundary-perf-$runId.json"
$result | ConvertTo-Json -Depth 6 | Out-File -FilePath $outFile -Encoding utf8

Write-Host "`nIntegration Boundary Performance Run Summary:"
Write-Host "  Sub-A (No-auth):    avg=$($statsA.avg_ms)ms p95=$($statsA.p95_ms)ms  auth_errors=$($statsA.auth_error_count)"
Write-Host "  Sub-B (Bad-token):  avg=$($statsB.avg_ms)ms p95=$($statsB.p95_ms)ms  auth_errors=$($statsB.auth_error_count)"
Write-Host "  Middleware overhead: ${middlewareOverheadMs}ms average"
Write-Host "  Scenario: $label"
Write-Host "`nResults written to: $outFile"
$result | ConvertTo-Json -Depth 6
