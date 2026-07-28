<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/dev/check-coverage-threshold.php <clover.xml> [minimumPercent]\n");
    exit(1);
}

$cloverPath = $argv[1];
$minimumPercent = isset($argv[2]) ? (float) $argv[2] : 20.0;

if (! is_file($cloverPath)) {
    fwrite(STDERR, "Coverage file not found: {$cloverPath}\n");
    exit(1);
}

$xml = simplexml_load_file($cloverPath);
if ($xml === false) {
    fwrite(STDERR, "Unable to parse coverage file: {$cloverPath}\n");
    exit(1);
}

$project = $xml->project;
$lineRate = isset($project['line-rate']) && $project['line-rate'] !== '' ? (float) $project['line-rate'] : null;

if ($lineRate === null) {
    $bestRate = null;
    $bestTotal = 0;

    foreach ($xml->xpath('//metrics') ?: [] as $metrics) {
        $pairs = [
            ['coveredstatements', 'statements'],
            ['coveredelements', 'elements'],
            ['coveredmethods', 'methods'],
        ];

        foreach ($pairs as [$coveredKey, $totalKey]) {
            if (! isset($metrics[$coveredKey], $metrics[$totalKey])) {
                continue;
            }

            $total = (int) $metrics[$totalKey];
            if ($total <= 0 || $total < $bestTotal) {
                continue;
            }

            $covered = (int) $metrics[$coveredKey];
            $bestTotal = $total;
            $bestRate = $covered / $total;
            break;
        }
    }

    $lineRate = $bestRate;
}

if ($lineRate === null) {
    fwrite(STDERR, "No usable coverage metrics found in coverage file: {$cloverPath}\n");
    exit(1);
}

$coveragePercent = round($lineRate * 100, 2);

echo "Measured line coverage: {$coveragePercent}%" . PHP_EOL;
echo "Required minimum: {$minimumPercent}%" . PHP_EOL;

if ($coveragePercent < $minimumPercent) {
    fwrite(STDERR, "Coverage threshold failed.\n");
    exit(1);
}

echo "Coverage threshold passed." . PHP_EOL;
