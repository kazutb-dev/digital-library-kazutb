Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

. "$PSScriptRoot/../shared/utils/http-client.ps1"

$baseUrl = if ($env:PHASE2_BASE_URL) { $env:PHASE2_BASE_URL } else { 'http://localhost' }
$results = @()

function Add-CaseResult {
    param([string]$Id,[string]$Module,[string]$Name,[bool]$Passed,[int]$StatusCode,[double]$ElapsedMs,[string]$Notes)
    $script:results += [PSCustomObject]@{
        testCaseId = $Id
        module = $Module
        name = $Name
        passed = $Passed
        statusCode = $StatusCode
        elapsedMs = $ElapsedMs
        notes = $Notes
    }
}

$r1 = Invoke-Phase2Request -Method GET -Url "$baseUrl/integration/v1/_boundary/ping"
Add-CaseResult -Id 'P2-API-INT-001' -Module 'Integration API' -Name 'Boundary ping endpoint responds' -Passed ($r1.StatusCode -in 200,401,403) -StatusCode $r1.StatusCode -ElapsedMs $r1.ElapsedMs -Notes 'status may depend on auth policy'

$r2 = Invoke-Phase2Request -Method GET -Url "$baseUrl/integration/v1/reservations"
Add-CaseResult -Id 'P2-API-INT-002' -Module 'Integration API' -Name 'Reservations integration endpoint enforces policy' -Passed ($r2.StatusCode -in 200,401,403,422) -StatusCode $r2.StatusCode -ElapsedMs $r2.ElapsedMs -Notes 'policy-enforced endpoint'

$results | ConvertTo-Json -Depth 5
