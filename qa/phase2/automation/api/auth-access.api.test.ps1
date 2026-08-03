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

$r1 = Invoke-Phase2Request -Method GET -Url "$baseUrl/api/v1/me"
Add-CaseResult -Id 'P2-API-AUTH-001' -Module 'Auth' -Name 'Anonymous access to /api/v1/me returns unauthorized' -Passed ($r1.StatusCode -in 401,403) -StatusCode $r1.StatusCode -ElapsedMs $r1.ElapsedMs -Notes $r1.Error

$r2 = Invoke-Phase2Request -Method GET -Url "$baseUrl/api/v1/account/reservations"
Add-CaseResult -Id 'P2-API-AUTH-002' -Module 'Auth' -Name 'Anonymous access to /api/v1/account/reservations returns unauthorized' -Passed ($r2.StatusCode -in 401,403) -StatusCode $r2.StatusCode -ElapsedMs $r2.ElapsedMs -Notes $r2.Error

$r3 = Invoke-Phase2Request -Method GET -Url "$baseUrl/api/v1/internal/circulation/summary"
Add-CaseResult -Id 'P2-API-AUTH-003' -Module 'Authorization' -Name 'Anonymous access to internal circulation endpoint is blocked' -Passed ($r3.StatusCode -in 401,403,404) -StatusCode $r3.StatusCode -ElapsedMs $r3.ElapsedMs -Notes $r3.Error

$results | ConvertTo-Json -Depth 5
