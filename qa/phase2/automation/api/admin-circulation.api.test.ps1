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

$r1 = Invoke-Phase2Request -Method GET -Url "$baseUrl/api/v1/internal/review/documents?limit=5"
Add-CaseResult -Id 'P2-API-OPS-001' -Module 'Circulation/Operations' -Name 'Internal review API blocked for anonymous access' -Passed ($r1.StatusCode -in 401,403,404) -StatusCode $r1.StatusCode -ElapsedMs $r1.ElapsedMs -Notes 'RBAC enforcement check'

$r2 = Invoke-Phase2Request -Method GET -Url "$baseUrl/admin/integrations"
Add-CaseResult -Id 'P2-API-OPS-002' -Module 'Admin Operations' -Name 'Admin integrations page is not publicly accessible' -Passed ($r2.StatusCode -in 302,401,403) -StatusCode $r2.StatusCode -ElapsedMs $r2.ElapsedMs -Notes 'admin guard check'

$results | ConvertTo-Json -Depth 5
