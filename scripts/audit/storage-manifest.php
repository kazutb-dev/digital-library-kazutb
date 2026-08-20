<?php

declare(strict_types=1);

/**
 * Read-only manifest of runtime-managed files. Recovery archives are excluded:
 * they have their own forensic hashes and are not application delivery data.
 */

$project = realpath(__DIR__.'/../..');
if ($project === false) {
    fwrite(STDERR, "Project root is unavailable.\n");
    exit(1);
}

$roots = [
    'private' => $project.'/storage/app/private',
    'public_uploads' => $project.'/storage/app/public',
    'built_assets' => $project.'/public/build',
];

$files = [];
foreach ($roots as $kind => $root) {
    if (! is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->isLink()) {
            continue;
        }

        $absolute = $file->getPathname();
        $relative = ltrim(substr($absolute, strlen($project)), DIRECTORY_SEPARATOR);
        $files[] = [
            'class' => $kind,
            'path' => str_replace(DIRECTORY_SEPARATOR, '/', $relative),
            'size' => $file->getSize(),
            'modified_utc' => gmdate(DATE_ATOM, $file->getMTime()),
            'sha256' => hash_file('sha256', $absolute),
        ];
    }
}

usort($files, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);

echo json_encode([
    'schema_version' => 1,
    'generated_at' => gmdate(DATE_ATOM),
    'project_root' => $project,
    'roots' => array_map(static fn (string $path): string => str_replace($project.'/', '', $path), $roots),
    'file_count' => count($files),
    'total_bytes' => array_sum(array_column($files, 'size')),
    'files' => $files,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
