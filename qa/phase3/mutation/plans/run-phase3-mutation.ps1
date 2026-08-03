param(
    [string]$RunId = (Get-Date -Format 'yyyyMMdd-HHmmss')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..\..')).Path
$metricsDir = Join-Path $repoRoot 'qa/phase3/metrics'
$resultsDir = Join-Path $repoRoot 'qa/phase3/mutation/results'
$logsDir = Join-Path $repoRoot 'qa/phase3/evidence/logs'

$mutants = @(
    [pscustomobject]@{
        mutant_id         = 'MUT-INTB-001'
        module            = 'Integration Boundary Middleware'
        component         = 'EnsureIntegrationBoundary::handle'
        file              = 'app/Http/Middleware/EnsureIntegrationBoundary.php'
        target            = 'Missing bearer token guard'
        mutation_type     = 'Guard condition inversion'
        original_behavior = 'Reject only when bearer token is empty.'
        mutated_behavior  = 'Reject when bearer token is present.'
        rationale         = 'Validates boundary auth gate correctness.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/IntegrationBoundarySkeletonTest.php', 'tests/Feature/Api/Integration/IntegrationRateLimitTest.php')
        old_snippet       = "if (trim(`$token) === '') {"
        new_snippet       = "if (trim(`$token) !== '') {"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-INTB-002'
        module            = 'Integration Boundary Middleware'
        component         = 'EnsureIntegrationBoundary::handle'
        file              = 'app/Http/Middleware/EnsureIntegrationBoundary.php'
        target            = 'Source-system validation'
        mutation_type     = 'Logical operator change'
        original_behavior = 'Accept only X-Source-System=crm.'
        mutated_behavior  = 'Reject requests where source-system is crm.'
        rationale         = 'Checks test sensitivity to source-system governance rule.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/IntegrationBoundarySkeletonTest.php', 'tests/Feature/Api/Integration/IntegrationRateLimitTest.php')
        old_snippet       = "if (`$sourceSystem !== 'crm') {"
        new_snippet       = "if (`$sourceSystem === 'crm') {"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-INTB-003'
        module            = 'Integration Boundary Middleware'
        component         = 'EnsureIntegrationBoundary::isTokenAllowed'
        file              = 'app/Http/Middleware/EnsureIntegrationBoundary.php'
        target            = 'Legacy allowlist fallback'
        mutation_type     = 'Return value modification'
        original_behavior = 'Allow non-empty bearer token when allowlist is empty.'
        mutated_behavior  = 'Deny all tokens when allowlist is empty.'
        rationale         = 'Tests whether successful integration flows would detect fallback breakage.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/IntegrationBoundarySkeletonTest.php', 'tests/Feature/Api/Integration/IntegrationRateLimitTest.php')
        old_snippet       = "            return true; // allowlist not configured — accept any token (legacy mode)"
        new_snippet       = "            return false; // MUTATION: deny tokens in legacy mode"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-RDR-001'
        module            = 'Integration Reservations Read API'
        component         = 'ReservationReadController::index'
        file              = 'app/Http/Controllers/Api/Integration/ReservationReadController.php'
        target            = 'Pagination validation'
        mutation_type     = 'Logical operator change'
        original_behavior = 'Reject if page OR per_page invalid.'
        mutated_behavior  = 'Reject only if both page AND per_page invalid.'
        rationale         = 'Verifies detection of weakened pagination validation.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/Integration/ReservationReadTest.php')
        old_snippet       = "if (`$page === false || `$perPage === false) {"
        new_snippet       = "if (`$page === false && `$perPage === false) {"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-RDR-002'
        module            = 'Integration Reservations Read API'
        component         = 'ReservationReadController::show'
        file              = 'app/Http/Controllers/Api/Integration/ReservationReadController.php'
        target            = 'Not-found branch'
        mutation_type     = 'Guard condition inversion'
        original_behavior = 'Return 404 when reservation is null.'
        mutated_behavior  = 'Return 404 when reservation exists.'
        rationale         = 'Verifies detail response correctness and negative path tests.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/Integration/ReservationReadTest.php')
        old_snippet       = "if (`$reservation === null) {"
        new_snippet       = "if (`$reservation !== null) {"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-RDR-003'
        module            = 'Integration Reservations Read API'
        component         = 'ReservationReadController::index'
        file              = 'app/Http/Controllers/Api/Integration/ReservationReadController.php'
        target            = 'user_id UUID validation'
        mutation_type     = 'Logical operator change'
        original_behavior = 'Reject non-UUID user_id filter.'
        mutated_behavior  = 'Reject only UUID user_id filter values.'
        rationale         = 'Evaluates validation test precision for invalid identifiers.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/Integration/ReservationReadTest.php')
        old_snippet       = "if (`$userIdInput !== null && ! Str::isUuid((string) `$userIdInput)) {"
        new_snippet       = "if (`$userIdInput !== null && Str::isUuid((string) `$userIdInput)) {"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-MUT-001'
        module            = 'Integration Reservations Mutate API'
        component         = 'ReservationMutateController::approve'
        file              = 'app/Http/Controllers/Api/Integration/ReservationMutateController.php'
        target            = 'Approve role gate'
        mutation_type     = 'Guard condition inversion'
        original_behavior = 'Deny approve without reservations.approve role.'
        mutated_behavior  = 'Deny approve when reservations.approve role exists.'
        rationale         = 'Checks role authorization test effectiveness for approve path.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/Integration/ReservationMutateTest.php')
        old_snippet       = "if (! `$this->hasOperatorRole(`$request, 'reservations.approve')) {"
        new_snippet       = "if (`$this->hasOperatorRole(`$request, 'reservations.approve')) {"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-MUT-002'
        module            = 'Integration Reservations Mutate API'
        component         = 'ReservationMutateController::approve'
        file              = 'app/Http/Controllers/Api/Integration/ReservationMutateController.php'
        target            = 'Idempotency-key guard'
        mutation_type     = 'Guard condition inversion'
        original_behavior = 'Reject when Idempotency-Key is missing.'
        mutated_behavior  = 'Reject when Idempotency-Key is present.'
        rationale         = 'Checks defensive header validation assertions.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/Integration/ReservationMutateTest.php')
        old_snippet       = "if (`$idempotencyKey === '') {"
        new_snippet       = "if (`$idempotencyKey !== '') {"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-MUT-003'
        module            = 'Integration Reservations Mutate API'
        component         = 'ReservationMutateController::reject'
        file              = 'app/Http/Controllers/Api/Integration/ReservationMutateController.php'
        target            = 'Reject payload required fields'
        mutation_type     = 'Logical operator change'
        original_behavior = 'Reject when cancel_origin OR cancel_reason_code is missing.'
        mutated_behavior  = 'Reject only when both fields are missing.'
        rationale         = 'Checks negative reject-path coverage for partial payload.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/Integration/ReservationMutateTest.php')
        old_snippet       = "if (`$cancelOrigin === '' || `$cancelReasonCode === '') {"
        new_snippet       = "if (`$cancelOrigin === '' && `$cancelReasonCode === '') {"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-MUT-004'
        module            = 'Integration Reservations Mutate API'
        component         = 'ReservationMutateController::context'
        file              = 'app/Http/Controllers/Api/Integration/ReservationMutateController.php'
        target            = 'Operator header mapping'
        mutation_type     = 'Key lookup alteration'
        original_behavior = 'Reads operator id from X-Operator-Id header.'
        mutated_behavior  = 'Reads operator id from misspelled header X-Operator-Idx.'
        rationale         = 'Tests whether current suite validates context payload details sent to service.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/Integration/ReservationMutateTest.php')
        old_snippet       = "'operatorId' => trim((string) `$request->header('X-Operator-Id', '')),"
        new_snippet       = "'operatorId' => trim((string) `$request->header('X-Operator-Idx', '')),"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-DOC-001'
        module            = 'Integration Document Management API'
        component         = 'DocumentManagementController::show'
        file              = 'app/Http/Controllers/Api/Integration/DocumentManagementController.php'
        target            = 'Document id validation'
        mutation_type     = 'Guard condition inversion'
        original_behavior = 'Reject non-UUID document ids.'
        mutated_behavior  = 'Reject UUID document ids.'
        rationale         = 'Ensures UUID validation tests kill inverted guards.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/Integration/DocumentManagementTest.php')
        old_snippet       = "if (! Str::isUuid(`$id)) {"
        new_snippet       = "if (Str::isUuid(`$id)) {"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-DOC-002'
        module            = 'Integration Document Management API'
        component         = 'DocumentManagementController::patch'
        file              = 'app/Http/Controllers/Api/Integration/DocumentManagementController.php'
        target            = 'No-mutable-fields guard'
        mutation_type     = 'Guard condition inversion'
        original_behavior = 'Reject only when patch payload has no mutable fields.'
        mutated_behavior  = 'Reject when mutable fields are present.'
        rationale         = 'Checks patch success assertions against guard inversion.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/Integration/DocumentManagementTest.php')
        old_snippet       = "if (`$validated === []) {"
        new_snippet       = "if (`$validated !== []) {"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-DOC-003'
        module            = 'Integration Document Management API'
        component         = 'DocumentManagementController::index'
        file              = 'app/Http/Controllers/Api/Integration/DocumentManagementController.php'
        target            = 'per_page upper bound rule'
        mutation_type     = 'Constant alteration'
        original_behavior = 'per_page maximum is 100.'
        mutated_behavior  = 'per_page maximum increased to 1000.'
        rationale         = 'Checks if boundary validation tests catch relaxed constraints.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/Integration/DocumentManagementTest.php')
        old_snippet       = "'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],"
        new_snippet       = "'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],"
    }
    [pscustomobject]@{
        mutant_id         = 'MUT-DOC-004'
        module            = 'Integration Document Management API'
        component         = 'DocumentManagementController::context'
        file              = 'app/Http/Controllers/Api/Integration/DocumentManagementController.php'
        target            = 'Operator header mapping'
        mutation_type     = 'Key lookup alteration'
        original_behavior = 'Reads operator id from X-Operator-Id header.'
        mutated_behavior  = 'Reads operator id from misspelled header X-Operator-Idx.'
        rationale         = 'Assesses whether tests verify service context payload semantics.'
        execution_method  = 'manual-scripted'
        tests             = @('tests/Feature/Api/Integration/DocumentManagementTest.php')
        old_snippet       = "'operator_id' => trim((string) `$request->header('X-Operator-Id', '')),"
        new_snippet       = "'operator_id' => trim((string) `$request->header('X-Operator-Idx', '')),"
    }
)

$mutantsPathCsv = Join-Path $metricsDir 'phase3-mutants.csv'
$mutantsPathJson = Join-Path $metricsDir 'phase3-mutants.json'
$resultsPathCsv = Join-Path $metricsDir 'phase3-mutation-results.csv'
$resultsPathJson = Join-Path $metricsDir 'phase3-mutation-results.json'
$rawRunPath = Join-Path $resultsDir ("mutation-run-$RunId.json")

$mutants | Select-Object mutant_id, module, component, file, target, mutation_type, original_behavior, mutated_behavior, rationale, execution_method, @{Name = 'tests_selected'; Expression = { ($_.tests -join ' | ') } } |
Export-Csv -Path $mutantsPathCsv -NoTypeInformation -Encoding UTF8

$mutants | Select-Object mutant_id, module, component, file, target, mutation_type, original_behavior, mutated_behavior, rationale, execution_method, tests |
ConvertTo-Json -Depth 6 |
Set-Content -Path $mutantsPathJson -Encoding UTF8

$results = @()

foreach ($mutant in $mutants) {
    $targetPath = Join-Path $repoRoot $mutant.file
    $logPath = Join-Path $logsDir ("phase3-mutation-$($mutant.mutant_id)-$RunId.log")

    $startAt = Get-Date
    $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
    $status = 'Error'
    $notes = ''
    $exitCode = -1

    $original = ''

    try {
        $original = Get-Content -Path $targetPath -Raw -Encoding UTF8

        if (-not $original.Contains($mutant.old_snippet)) {
            throw "Old snippet not found in $($mutant.file)"
        }

        $mutated = $original.Replace($mutant.old_snippet, $mutant.new_snippet)
        if ($mutated -eq $original) {
            throw "Mutation replacement made no change in $($mutant.file)"
        }

        Set-Content -Path $targetPath -Value $mutated -Encoding UTF8

        $phpArgs = @('artisan', 'test') + $mutant.tests
        $output = & php @phpArgs 2>&1 | Out-String
        $exitCode = $LASTEXITCODE

        $status = if ($exitCode -eq 0) { 'Survived' } else { 'Killed' }

        $logBody = @(
            "run_id=$RunId"
            "mutant_id=$($mutant.mutant_id)"
            "module=$($mutant.module)"
            "status=$status"
            "exit_code=$exitCode"
            "file=$($mutant.file)"
            "target=$($mutant.target)"
            "mutation_type=$($mutant.mutation_type)"
            "tests=$($mutant.tests -join ', ')"
            "started_at=$($startAt.ToString('o'))"
            ""
            "--- php artisan test output ---"
            $output.TrimEnd()
        ) -join "`r`n"

        Set-Content -Path $logPath -Value $logBody -Encoding UTF8
    }
    catch {
        $status = 'Error'
        $notes = $_.Exception.Message

        $errorBody = @(
            "run_id=$RunId"
            "mutant_id=$($mutant.mutant_id)"
            "module=$($mutant.module)"
            "status=Error"
            "exit_code=$exitCode"
            "file=$($mutant.file)"
            "target=$($mutant.target)"
            "mutation_type=$($mutant.mutation_type)"
            "tests=$($mutant.tests -join ', ')"
            "started_at=$($startAt.ToString('o'))"
            ""
            "error=$notes"
        ) -join "`r`n"

        Set-Content -Path $logPath -Value $errorBody -Encoding UTF8
    }
    finally {
        if ($original -ne '') {
            Set-Content -Path $targetPath -Value $original -Encoding UTF8
        }
    }

    $stopwatch.Stop()

    $results += [pscustomobject]@{
        run_id         = $RunId
        mutant_id      = $mutant.mutant_id
        module         = $mutant.module
        component      = $mutant.component
        file           = $mutant.file
        mutation_type  = $mutant.mutation_type
        tests_executed = ($mutant.tests -join ' | ')
        status         = $status
        exit_code      = $exitCode
        elapsed_ms     = [math]::Round($stopwatch.Elapsed.TotalMilliseconds, 2)
        started_at     = $startAt.ToString('o')
        log_file       = (Resolve-Path -Relative $logPath)
        notes          = $notes
    }
}

$results | Export-Csv -Path $resultsPathCsv -NoTypeInformation -Encoding UTF8
$results | ConvertTo-Json -Depth 6 | Set-Content -Path $resultsPathJson -Encoding UTF8

[pscustomobject]@{
    run_id       = $RunId
    generated_at = (Get-Date).ToString('o')
    mutant_count = $mutants.Count
    results      = $results
} | ConvertTo-Json -Depth 8 | Set-Content -Path $rawRunPath -Encoding UTF8

Write-Host "Mutation campaign completed: run_id=$RunId"
Write-Host "Mutants: $($mutants.Count)"
Write-Host "Results CSV: $resultsPathCsv"
Write-Host "Results JSON: $resultsPathJson"
