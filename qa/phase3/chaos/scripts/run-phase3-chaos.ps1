param(
    [string]$BaseUrl = 'http://localhost',
    [string]$RunId = (Get-Date -Format 'yyyyMMdd-HHmmss')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..\..')).Path
$resultsDir = Join-Path $repoRoot 'qa/phase3/chaos/results'
$logsDir = Join-Path $repoRoot 'qa/phase3/evidence/logs'

if (-not (Test-Path $resultsDir)) { New-Item -ItemType Directory -Path $resultsDir | Out-Null }
if (-not (Test-Path $logsDir)) { New-Item -ItemType Directory -Path $logsDir | Out-Null }

$integrationHeaders = @{
    Authorization            = 'Bearer integration-test-token'
    'X-Request-Id'           = 'chaos-req-001'
    'X-Correlation-Id'       = 'chaos-corr-001'
    'X-Source-System'        = 'crm'
    'X-Operator-Id'          = 'chaos-op-01'
    'X-Operator-Roles'       = 'reservations.approve,reservations.reject'
    'X-Operator-Org-Context' = '{"branch_id":"main"}'
}

function Invoke-ChaosRequest {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)][int]$TimeoutSec,
        [int]$InjectedDelayMs = 0,
        [hashtable]$Headers = $null
    )

    if ($InjectedDelayMs -gt 0) {
        $delayTimer = [System.Diagnostics.Stopwatch]::StartNew()
        while ($delayTimer.ElapsedMilliseconds -lt $InjectedDelayMs) {
            [System.Math]::Sqrt(12345.6789) | Out-Null
        }
        $delayTimer.Stop()
    }

    $timer = [System.Diagnostics.Stopwatch]::StartNew()
    $httpStatus = ''
    $success = $false
    $reachable = $false
    $errorType = ''
    $errorMessage = ''

    try {
        if ($Headers -ne $null) {
            $resp = Invoke-WebRequest -Uri $Url -Method GET -TimeoutSec $TimeoutSec -Headers $Headers
        }
        else {
            $resp = Invoke-WebRequest -Uri $Url -Method GET -TimeoutSec $TimeoutSec
        }
        $httpStatus = [string]$resp.StatusCode
        $success = $resp.StatusCode -ge 200 -and $resp.StatusCode -lt 400
        $reachable = $true
    }
    catch {
        $ex = $_.Exception
        if ($ex.PSObject.Properties.Name -contains 'Response' -and $null -ne $ex.Response) {
            try {
                if ($ex.Response.PSObject.Properties.Name -contains 'StatusCode') {
                    $httpStatus = [string][int]$ex.Response.StatusCode
                    $reachable = $true
                }
            }
            catch { }
        }

        $errorType = $ex.GetType().FullName
        $errorMessage = $ex.Message

        if ($httpStatus -eq '') {
            if ($errorMessage -match 'timed out') {
                $httpStatus = 'TIMEOUT'
            }
            elseif ($errorMessage -match 'actively refused') {
                $httpStatus = 'CONNECTION_REFUSED'
            }
            else {
                $httpStatus = 'REQUEST_ERROR'
            }
        }
    }
    finally {
        $timer.Stop()
    }

    return [pscustomobject]@{
        timestamp_utc     = (Get-Date).ToUniversalTime().ToString('o')
        url               = $Url
        timeout_sec       = $TimeoutSec
        injected_delay_ms = $InjectedDelayMs
        elapsed_ms        = [math]::Round($timer.Elapsed.TotalMilliseconds, 2)
        http_status       = $httpStatus
        success           = $success
        reachable         = $reachable
        error_type        = $errorType
        error_message     = $errorMessage
    }
}

function Start-CpuStressJob {
    param([int]$DurationSec = 20)

    return Start-Job -ScriptBlock {
        param($Seconds)
        $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
        while ($stopwatch.Elapsed.TotalSeconds -lt $Seconds) {
            1..50000 | ForEach-Object { [Math]::Sqrt($_) } | Out-Null
        }
        $stopwatch.Stop()
        'completed'
    } -ArgumentList $DurationSec
}

$scenarios = @(
    [pscustomobject]@{
        scenario_id               = 'CHS-001-API-DOWN'
        target_module             = 'Catalog/Public API'
        target_component          = 'Landing endpoint availability'
        fault_type                = 'API downtime simulation'
        dataset_type              = 'bounded synthetic'
        fault_url                 = 'http://127.0.0.1:65535/api/v1/landing'
        recovery_url              = "$BaseUrl/api/v1/landing"
        timeout_sec_fault         = 3
        timeout_sec_recovery      = 15
        injected_delay_ms_fault   = 0
        fault_requests            = 6
        recovery_requests         = 4
        use_cpu_stress            = $false
        propagation_probe_url     = "$BaseUrl/api/integration/v1/_boundary/ping"
        propagation_probe_headers = $integrationHeaders
    }
    [pscustomobject]@{
        scenario_id               = 'CHS-002-DB-SLOWDOWN'
        target_module             = 'Catalog DB API'
        target_component          = 'Catalog DB query path'
        fault_type                = 'Database slowdown proxy (client timeout)'
        dataset_type              = 'bounded synthetic'
        fault_url                 = "$BaseUrl/api/v1/catalog-db"
        recovery_url              = "$BaseUrl/api/v1/catalog-db"
        timeout_sec_fault         = 1
        timeout_sec_recovery      = 15
        injected_delay_ms_fault   = 0
        fault_requests            = 6
        recovery_requests         = 4
        use_cpu_stress            = $false
        propagation_probe_url     = "$BaseUrl/api/v1/landing"
        propagation_probe_headers = $null
    }
    [pscustomobject]@{
        scenario_id               = 'CHS-003-NET-LATENCY'
        target_module             = 'External API Path'
        target_component          = 'Landing endpoint via synthetic latency'
        fault_type                = 'Network latency injection (client-side bounded model)'
        dataset_type              = 'synthetic fault model'
        fault_url                 = "$BaseUrl/api/v1/landing"
        recovery_url              = "$BaseUrl/api/v1/landing"
        timeout_sec_fault         = 15
        timeout_sec_recovery      = 15
        injected_delay_ms_fault   = 1500
        fault_requests            = 6
        recovery_requests         = 4
        use_cpu_stress            = $false
        propagation_probe_url     = "$BaseUrl/api/integration/v1/_boundary/ping"
        propagation_probe_headers = $integrationHeaders
    }
    [pscustomobject]@{
        scenario_id               = 'CHS-004-CPU-PRESSURE'
        target_module             = 'Web/API runtime'
        target_component          = 'Landing endpoint under local CPU pressure'
        fault_type                = 'Resource exhaustion proxy (CPU pressure)'
        dataset_type              = 'bounded synthetic'
        fault_url                 = "$BaseUrl/api/v1/landing"
        recovery_url              = "$BaseUrl/api/v1/landing"
        timeout_sec_fault         = 15
        timeout_sec_recovery      = 15
        injected_delay_ms_fault   = 0
        fault_requests            = 6
        recovery_requests         = 4
        use_cpu_stress            = $true
        propagation_probe_url     = "$BaseUrl/api/integration/v1/_boundary/ping"
        propagation_probe_headers = $integrationHeaders
    }
)

$allRequests = @()
$scenarioSummary = @()

foreach ($scenario in $scenarios) {
    $logPath = Join-Path $logsDir ("phase3-chaos-$($scenario.scenario_id)-$RunId.log")
    $lines = @()
    $lines += "run_id=$RunId"
    $lines += "scenario_id=$($scenario.scenario_id)"
    $lines += "fault_type=$($scenario.fault_type)"
    $lines += "target_component=$($scenario.target_component)"
    $lines += "started_at_utc=$((Get-Date).ToUniversalTime().ToString('o'))"

    $cpuJob = $null
    if ($scenario.use_cpu_stress) {
        $cpuJob = Start-CpuStressJob -DurationSec 25
        $lines += "cpu_stress_job_started=true"
    }

    $faultRequests = @()
    for ($i = 1; $i -le $scenario.fault_requests; $i++) {
        $r = Invoke-ChaosRequest -Url $scenario.fault_url -TimeoutSec $scenario.timeout_sec_fault -InjectedDelayMs $scenario.injected_delay_ms_fault
        $faultRequests += $r
        $allRequests += [pscustomobject]@{
            run_id            = $RunId
            scenario_id       = $scenario.scenario_id
            phase             = 'fault'
            request_index     = $i
            timestamp_utc     = $r.timestamp_utc
            endpoint          = $r.url
            timeout_sec       = $r.timeout_sec
            injected_delay_ms = $r.injected_delay_ms
            elapsed_ms        = $r.elapsed_ms
            http_status       = $r.http_status
            success           = $r.success
            reachable         = $r.reachable
            error_type        = $r.error_type
            error_message     = $r.error_message
        }
        $lines += "fault_req_$i status=$($r.http_status) success=$($r.success) elapsed_ms=$($r.elapsed_ms)"
    }

    if ($scenario.use_cpu_stress -and $null -ne $cpuJob) {
        Stop-Job -Job $cpuJob -ErrorAction SilentlyContinue | Out-Null
        Remove-Job -Job $cpuJob -Force -ErrorAction SilentlyContinue | Out-Null
        $lines += "cpu_stress_job_stopped=true"
    }

    $faultRemovedAt = [DateTime]::UtcNow

    $recoveryRequests = @()
    $firstRecoverySuccessAt = $null
    for ($j = 1; $j -le $scenario.recovery_requests; $j++) {
        $rr = Invoke-ChaosRequest -Url $scenario.recovery_url -TimeoutSec $scenario.timeout_sec_recovery -InjectedDelayMs 0
        $recoveryRequests += $rr
        $allRequests += [pscustomobject]@{
            run_id            = $RunId
            scenario_id       = $scenario.scenario_id
            phase             = 'recovery'
            request_index     = $j
            timestamp_utc     = $rr.timestamp_utc
            endpoint          = $rr.url
            timeout_sec       = $rr.timeout_sec
            injected_delay_ms = $rr.injected_delay_ms
            elapsed_ms        = $rr.elapsed_ms
            http_status       = $rr.http_status
            success           = $rr.success
            reachable         = $rr.reachable
            error_type        = $rr.error_type
            error_message     = $rr.error_message
        }
        $lines += "recovery_req_$j status=$($rr.http_status) success=$($rr.success) elapsed_ms=$($rr.elapsed_ms)"

        if ($rr.success -and $null -eq $firstRecoverySuccessAt) {
            $firstRecoverySuccessAt = [DateTime]::Parse($rr.timestamp_utc).ToUniversalTime()
        }
    }

    $probe = Invoke-ChaosRequest -Url $scenario.propagation_probe_url -TimeoutSec 8 -InjectedDelayMs 0 -Headers $scenario.propagation_probe_headers

    $faultSuccessCount = @($faultRequests | Where-Object { $_.success }).Count
    $faultErrorCount = $scenario.fault_requests - $faultSuccessCount
    $recoverySuccessCount = @($recoveryRequests | Where-Object { $_.success }).Count

    $faultAvailability = if ($scenario.fault_requests -gt 0) {
        [math]::Round((100.0 * $faultSuccessCount / $scenario.fault_requests), 2)
    }
    else { 0 }

    $recoveryAvailability = if ($scenario.recovery_requests -gt 0) {
        [math]::Round((100.0 * $recoverySuccessCount / $scenario.recovery_requests), 2)
    }
    else { 0 }

    $faultAvgMs = [math]::Round((($faultRequests | Measure-Object -Property elapsed_ms -Average).Average), 2)
    $recoveryAvgMs = [math]::Round((($recoveryRequests | Measure-Object -Property elapsed_ms -Average).Average), 2)

    $mttrMs = if ($null -ne $firstRecoverySuccessAt) {
        [math]::Round(($firstRecoverySuccessAt - $faultRemovedAt).TotalMilliseconds, 2)
    }
    else {
        -1
    }

    $degradationRatio = if ($recoveryAvgMs -gt 0) {
        [math]::Round(($faultAvgMs / $recoveryAvgMs), 3)
    }
    else {
        0
    }

    $propagationClass = if ($probe.reachable) { 'isolated' } else { 'cascading' }

    $scenarioSummary += [pscustomobject]@{
        run_id                              = $RunId
        scenario_id                         = $scenario.scenario_id
        target_module                       = $scenario.target_module
        target_component                    = $scenario.target_component
        fault_type                          = $scenario.fault_type
        dataset_type                        = $scenario.dataset_type
        fault_requests                      = $scenario.fault_requests
        fault_success_count                 = $faultSuccessCount
        fault_error_count                   = $faultErrorCount
        fault_availability_pct              = $faultAvailability
        fault_avg_ms                        = $faultAvgMs
        recovery_requests                   = $scenario.recovery_requests
        recovery_success_count              = $recoverySuccessCount
        recovery_availability_pct           = $recoveryAvailability
        recovery_avg_ms                     = $recoveryAvgMs
        degradation_ratio_fault_vs_recovery = $degradationRatio
        mttr_ms                             = $mttrMs
        propagation_probe_url               = $scenario.propagation_probe_url
        propagation_probe_status            = $probe.http_status
        propagation_probe_reachable         = $probe.reachable
        propagation_class                   = $propagationClass
        recovered                           = ($recoverySuccessCount -gt 0)
        timestamp_utc                       = (Get-Date).ToUniversalTime().ToString('o')
    }

    $lines += "fault_availability_pct=$faultAvailability"
    $lines += "recovery_availability_pct=$recoveryAvailability"
    $lines += "fault_avg_ms=$faultAvgMs"
    $lines += "recovery_avg_ms=$recoveryAvgMs"
    $lines += "degradation_ratio_fault_vs_recovery=$degradationRatio"
    $lines += "mttr_ms=$mttrMs"
    $lines += "propagation_probe_status=$($probe.http_status)"
    $lines += "propagation_class=$propagationClass"
    $lines += "completed_at_utc=$((Get-Date).ToUniversalTime().ToString('o'))"

    Set-Content -Path $logPath -Value ($lines -join "`r`n") -Encoding UTF8
}

$rawRequestsPath = Join-Path $resultsDir ("phase3-chaos-requests-$RunId.csv")
$rawRequestsJsonPath = Join-Path $resultsDir ("phase3-chaos-requests-$RunId.json")
$summaryPath = Join-Path $resultsDir ("phase3-chaos-summary-$RunId.csv")
$summaryJsonPath = Join-Path $resultsDir ("phase3-chaos-summary-$RunId.json")

$allRequests | Export-Csv -Path $rawRequestsPath -NoTypeInformation -Encoding UTF8
$allRequests | ConvertTo-Json -Depth 7 | Set-Content -Path $rawRequestsJsonPath -Encoding UTF8
$scenarioSummary | Export-Csv -Path $summaryPath -NoTypeInformation -Encoding UTF8
$scenarioSummary | ConvertTo-Json -Depth 7 | Set-Content -Path $summaryJsonPath -Encoding UTF8

Write-Host "Chaos run completed: run_id=$RunId"
Write-Host "Scenario summary: $summaryPath"
Write-Host "Raw request log:  $rawRequestsPath"
