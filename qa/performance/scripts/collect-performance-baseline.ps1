param(
	[ValidateSet('before', 'after')]
	[string]$Phase = 'before',
	[string]$OutputCsv = '',
	[string]$OutputMarkdown = ''
)

$ErrorActionPreference = 'Stop'

function Get-Percentile {
	param(
		[double[]]$Values,
		[double]$Percentile
	)

	if (-not $Values -or $Values.Count -eq 0) {
		return 0
	}

	$sorted = $Values | Sort-Object
	$rank = [Math]::Ceiling($Percentile * $sorted.Count)
	$index = [Math]::Max(0, [Math]::Min($sorted.Count - 1, $rank - 1))
	return [double]$sorted[$index]
}

function Invoke-CurlTiming {
	param(
		[string]$Url
	)

	$raw = ''
	try {
		$raw = & curl.exe --connect-timeout 2 --max-time 6 -sS -o NUL -w "%{time_starttransfer},%{time_total},%{http_code}" $Url 2>$null
	}
 catch {
		$raw = ''
	}

	if ([string]::IsNullOrWhiteSpace($raw)) {
		return [PSCustomObject]@{
			TTFB       = 6.0
			Total      = 6.0
			StatusCode = 0
		}
	}

	$parts = $raw -split ','
	if ($parts.Count -lt 3) {
		return [PSCustomObject]@{
			TTFB       = 6.0
			Total      = 6.0
			StatusCode = 0
		}
	}

	[PSCustomObject]@{
		TTFB       = [double]$parts[0]
		Total      = [double]$parts[1]
		StatusCode = [int]$parts[2]
	}
}

function Invoke-LatencySeries {
	param(
		[string]$Url,
		[int]$Iterations = 25
	)

	$values = @()
	for ($i = 0; $i -lt $Iterations; $i++) {
		$timing = Invoke-CurlTiming -Url $Url
		$values += $timing.Total
	}

	return , $values
}

function Get-DbExplainTiming {
	param(
		[string]$Sql
	)

	$planSql = "EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) $Sql"
	$output = $planSql | docker compose exec -T postgres sh -lc 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
	$line = ($output | Select-String -Pattern 'Execution Time:').Line
	if (-not $line) {
		return [PSCustomObject]@{ Query = $Sql; Ms = 0.0 }
	}

	$ms = 0.0
	if ($line -match 'Execution Time:\s+([0-9.]+)\s+ms') {
		$ms = [double]$Matches[1]
	}

	[PSCustomObject]@{ Query = $Sql; Ms = $ms }
}

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Resolve-Path (Join-Path $scriptDir '..\..\..')
Set-Location $repoRoot

$timestamp = Get-Date -Format 'yyyy-MM-ddTHH:mm:ssK'

if ([string]::IsNullOrWhiteSpace($OutputCsv)) {
	$OutputCsv = "qa/metrics/performance-baseline-$Phase.csv"
}

if ([string]::IsNullOrWhiteSpace($OutputMarkdown)) {
	$OutputMarkdown = "qa/performance/results/baseline-$Phase.md"
}

$outputCsvPath = Join-Path $repoRoot $OutputCsv
$outputMarkdownPath = Join-Path $repoRoot $OutputMarkdown

New-Item -ItemType Directory -Force -Path (Split-Path -Parent $outputCsvPath) | Out-Null
New-Item -ItemType Directory -Force -Path (Split-Path -Parent $outputMarkdownPath) | Out-Null

Write-Host "[perf] Restarting stack for cold-start measurement..."
$startupWatch = [System.Diagnostics.Stopwatch]::StartNew()
docker compose down --remove-orphans | Out-Null
docker compose up -d postgres app frontend-dev | Out-Null

& curl.exe -sS --retry 120 --retry-delay 1 --retry-all-errors --max-time 5 -o NUL http://localhost/
$startupWatch.Stop()
$startupSeconds = [Math]::Round($startupWatch.Elapsed.TotalSeconds, 3)

Write-Host "[perf] Measuring home page cold/warm timings..."
$homeUrl = 'http://localhost/'
$homeCold = Invoke-CurlTiming -Url $homeUrl

$homeWarmSeries = @()
for ($i = 0; $i -lt 8; $i++) {
	$homeWarmSeries += Invoke-CurlTiming -Url $homeUrl
}

$homeWarmTtfbAvg = [Math]::Round((($homeWarmSeries | Measure-Object -Property TTFB -Average).Average), 4)
$homeWarmTotalAvg = [Math]::Round((($homeWarmSeries | Measure-Object -Property Total -Average).Average), 4)

Write-Host "[perf] Measuring API p50/p95..."
$apiEndpoints = @(
	@{ Name = 'catalog-db'; Url = 'http://localhost/api/v1/catalog-db?limit=20&sort=popular' },
	@{ Name = 'subjects'; Url = 'http://localhost/api/v1/subjects' },
	@{ Name = 'landing'; Url = 'http://localhost/api/v1/landing' }
)

$apiMetrics = @()
foreach ($endpoint in $apiEndpoints) {
	$samples = Invoke-LatencySeries -Url $endpoint.Url -Iterations 10
	$p50 = [Math]::Round((Get-Percentile -Values $samples -Percentile 0.50), 4)
	$p95 = [Math]::Round((Get-Percentile -Values $samples -Percentile 0.95), 4)
	$avg = [Math]::Round((($samples | Measure-Object -Average).Average), 4)
	$apiMetrics += [PSCustomObject]@{
		Name = $endpoint.Name
		Url  = $endpoint.Url
		P50  = $p50
		P95  = $p95
		Avg  = $avg
	}
}

Write-Host "[perf] Measuring representative DB query timings..."
$dbQueries = @(
	"SELECT COUNT(*) FROM app.document_detail_v;",
	"SELECT document_id, title_display FROM app.document_detail_v ORDER BY publication_year DESC NULLS LAST LIMIT 30;",
	"SELECT document_id, title_display FROM app.document_detail_v WHERE LOWER(COALESCE(title_display, title_raw, '')) LIKE '%data%' LIMIT 30;"
)

$dbTimings = @()
foreach ($query in $dbQueries) {
	$dbTimings += Get-DbExplainTiming -Sql $query
}

$dbTop = $dbTimings | Sort-Object -Property Ms -Descending

Write-Host "[perf] Capturing docker CPU/RAM snapshot..."
$dockerStatsRaw = docker stats --no-stream --format "{{.Name}},{{.CPUPerc}},{{.MemUsage}}"
$dockerStats = @()
foreach ($line in $dockerStatsRaw) {
	$parts = $line -split ',', 3
	if ($parts.Count -eq 3) {
		$dockerStats += [PSCustomObject]@{
			Name = $parts[0]
			Cpu  = $parts[1]
			Mem  = $parts[2]
		}
	}
}

Write-Host "[perf] Checking profiler/debug integrations availability..."
$hasTelescope = Test-Path (Join-Path $repoRoot 'vendor/laravel/telescope')
$hasDebugbar = Test-Path (Join-Path $repoRoot 'vendor/barryvdh/laravel-debugbar')

$records = New-Object System.Collections.Generic.List[object]
function Add-Record {
	param(
		[string]$Metric,
		[string]$Value,
		[string]$Unit,
		[string]$Notes
	)

	$records.Add([PSCustomObject]@{
			timestamp = $timestamp
			phase     = $Phase
			metric    = $Metric
			value     = $Value
			unit      = $Unit
			notes     = $Notes
		}) | Out-Null
}

Add-Record -Metric 'docker_startup_time' -Value $startupSeconds -Unit 's' -Notes 'compose down + up postgres,app,frontend-dev until app healthy/running'
Add-Record -Metric 'home_cold_ttfb' -Value ([Math]::Round($homeCold.TTFB, 4)) -Unit 's' -Notes 'first request after stack restart'
Add-Record -Metric 'home_cold_total' -Value ([Math]::Round($homeCold.Total, 4)) -Unit 's' -Notes 'first request after stack restart'
Add-Record -Metric 'home_warm_ttfb_avg' -Value $homeWarmTtfbAvg -Unit 's' -Notes 'average of 8 sequential requests'
Add-Record -Metric 'home_warm_total_avg' -Value $homeWarmTotalAvg -Unit 's' -Notes 'average of 8 sequential requests'

foreach ($api in $apiMetrics) {
	Add-Record -Metric ("api_{0}_p50" -f $api.Name) -Value $api.P50 -Unit 's' -Notes $api.Url
	Add-Record -Metric ("api_{0}_p95" -f $api.Name) -Value $api.P95 -Unit 's' -Notes $api.Url
	Add-Record -Metric ("api_{0}_avg" -f $api.Name) -Value $api.Avg -Unit 's' -Notes $api.Url
}

$dbRank = 1
foreach ($db in $dbTop) {
	Add-Record -Metric ("db_query_top{0}" -f $dbRank) -Value ([Math]::Round($db.Ms, 3)) -Unit 'ms' -Notes $db.Query
	$dbRank++
}

foreach ($stat in $dockerStats) {
	Add-Record -Metric ("container_cpu_{0}" -f $stat.Name) -Value $stat.Cpu -Unit 'percent' -Notes 'docker stats snapshot'
	Add-Record -Metric ("container_mem_{0}" -f $stat.Name) -Value $stat.Mem -Unit 'raw' -Notes 'docker stats snapshot'
}

Add-Record -Metric 'laravel_telescope_available' -Value ($(if ($hasTelescope) { '1' } else { '0' })) -Unit 'bool' -Notes 'vendor/laravel/telescope presence'
Add-Record -Metric 'laravel_debugbar_available' -Value ($(if ($hasDebugbar) { '1' } else { '0' })) -Unit 'bool' -Notes 'vendor/barryvdh/laravel-debugbar presence'

$records | Export-Csv -Path $outputCsvPath -NoTypeInformation -Encoding UTF8

$md = @()
$md += "# Performance Baseline ($Phase)"
$md += ""
$md += "Timestamp: $timestamp"
$md += ""
$md += "## Core Metrics"
$md += ""
$md += "| Metric | Value | Unit | Notes |"
$md += "|---|---:|---|---|"
foreach ($r in $records) {
	$md += "| $($r.metric) | $($r.value) | $($r.unit) | $($r.notes) |"
}

$md -join [Environment]::NewLine | Set-Content -Path $outputMarkdownPath -Encoding UTF8

Write-Host "[perf] Wrote CSV: $OutputCsv"
Write-Host "[perf] Wrote Markdown: $OutputMarkdown"
