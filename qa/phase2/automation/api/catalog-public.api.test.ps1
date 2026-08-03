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

$r1 = Invoke-Phase2Request -Method GET -Url "$baseUrl/api/v1/landing"
$j1 = Convert-BodyToJson -Body $r1.Body
Add-CaseResult -Id 'P2-API-CAT-001' -Module 'Catalog/Public API' -Name 'Landing API returns data' -Passed ($r1.StatusCode -eq 200 -and $null -ne $j1) -StatusCode $r1.StatusCode -ElapsedMs $r1.ElapsedMs -Notes 'expects JSON payload'

$r2 = Invoke-Phase2Request -Method GET -Url "$baseUrl/api/v1/catalog-db?limit=5&sort=popular"
$j2 = Convert-BodyToJson -Body $r2.Body
$hasData = $false
if ($null -ne $j2 -and $j2.PSObject.Properties.Name -contains 'data') { $hasData = $true }
Add-CaseResult -Id 'P2-API-CAT-002' -Module 'Catalog/Public API' -Name 'Catalog API returns data collection' -Passed ($r2.StatusCode -eq 200 -and $hasData) -StatusCode $r2.StatusCode -ElapsedMs $r2.ElapsedMs -Notes 'expects data field'

$r3 = Invoke-Phase2Request -Method GET -Url "$baseUrl/api/v1/subjects"
$j3 = Convert-BodyToJson -Body $r3.Body
Add-CaseResult -Id 'P2-API-CAT-003' -Module 'Catalog/Public API' -Name 'Subjects API responds successfully' -Passed ($r3.StatusCode -eq 200 -and $null -ne $j3) -StatusCode $r3.StatusCode -ElapsedMs $r3.ElapsedMs -Notes 'subjects endpoint'

$results | ConvertTo-Json -Depth 5
