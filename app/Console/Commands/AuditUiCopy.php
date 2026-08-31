<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditUiCopy extends Command
{
    protected $signature = 'library:ui-copy-audit
        {--json= : Write the complete machine-readable report to this path}
        {--warnings-as-errors : Return a failure code when warnings are present}';

    protected $description = 'Audit user-facing source copy for internal requirement labels, decorative symbols, legacy branding and developer text';

    /** @var array<string, string> */
    private const CRITICAL_PATTERNS = [
        'legacy_brand' => '/(?<![\pL\pN])(?:KazUTB(?:\s+(?:Smart\s+)?Library)?|KazTBU|КазУТБ|КазТБУ|ҚазУТБ|ҚазТБУ)(?![\pL\pN])/iu',
        'requirement_reference' => '/(?:§\s*\d+(?:\.\d+)*|пункт\s+ТЗ|раздел\s+ДИР|согласно\s+§|реализовано\s+по\s+разделу)/iu',
        'decorative_symbol' => '/[§¶●◉▪▫◆◇▶►→⇒✓✔✕❌👉📌⚠🔎📊⚖🧩📈🧾]/u',
        'developer_copy' => '/\b(?:TODO|Coming\s+soon|MVP|mock|legacy\s+test|synthetic|test\s+mode)\b|будет\s+реализован[ао]?\s+позже|следующ(?:ий|ая)\s+(?:этап|фаза)|техническая\s+заглушка|тестовый\s+пользователь|комментар(?:ий|ии)\s+агента|отч[её]т(?:ы)?\s+Codex/iu',
        'absolute_home_path' => '#/home/(?:admtutor|admdev|projects)/#u',
        'broken_encoding' => '/(?:Ð.|Ñ.|Ò.|Ó.|Â.|Ã.){2,}/u',
    ];

    /** @var array<string, string> */
    private const WARNING_PATTERNS = [
        'placeholder_copy' => '/\b(?:lorem\s+ipsum|placeholder\s+text)\b|текст-заглушка/iu',
    ];

    /** @var list<string> */
    private const EXTENSIONS = ['php', 'js', 'jsx', 'ts', 'tsx', 'vue'];

    public function handle(): int
    {
        $findings = [];
        foreach ($this->sourceRoots() as $root) {
            if (! File::isDirectory($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                if (! $this->isScannable($file->getFilename(), $file->getExtension())) {
                    continue;
                }

                $relative = str_replace(base_path().'/', '', $file->getPathname());
                if (in_array($relative, [
                    'app/Console/Commands/AuditUiCopy.php',
                    'app/Console/Commands/AuditLibraryI18n.php',
                    'app/Console/Commands/SmokeLibraryRoles.php',
                ], true)) {
                    continue;
                }

                $source = $this->withoutNonRenderedComments(File::get($file->getPathname()));
                foreach (preg_split('/\R/u', $source) ?: [] as $index => $line) {
                    if (str_starts_with(ltrim($line), '//')) {
                        continue;
                    }
                    foreach (self::CRITICAL_PATTERNS as $rule => $pattern) {
                        if (preg_match($pattern, $line) !== 1 || $this->isAllowed($rule, $relative, $line)) {
                            continue;
                        }
                        $findings[] = $this->finding('critical', $rule, $relative, $index + 1, $line);
                    }
                    foreach (self::WARNING_PATTERNS as $rule => $pattern) {
                        if (preg_match($pattern, $line) !== 1 || $this->isAllowed($rule, $relative, $line)) {
                            continue;
                        }
                        $findings[] = $this->finding('warning', $rule, $relative, $index + 1, $line);
                    }
                }
            }
        }

        $critical = array_values(array_filter($findings, fn (array $row): bool => $row['severity'] === 'critical'));
        $warnings = array_values(array_filter($findings, fn (array $row): bool => $row['severity'] === 'warning'));

        $this->table(
            ['Severity', 'Rule', 'Location', 'Excerpt'],
            array_map(fn (array $row): array => [
                $row['severity'],
                $row['rule'],
                $row['path'].':'.$row['line'],
                mb_strimwidth($row['excerpt'], 0, 92, '…'),
            ], array_slice($findings, 0, 200)),
        );
        $this->line(sprintf('critical=%d warnings=%d files=%d', count($critical), count($warnings), count(array_unique(array_column($findings, 'path')))));

        if (is_string($this->option('json')) && $this->option('json') !== '') {
            $path = (string) $this->option('json');
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode([
                'generated_at' => now('UTC')->toIso8601String(),
                'critical_count' => count($critical),
                'warning_count' => count($warnings),
                'findings' => $findings,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        }

        return $critical === [] && (! $this->option('warnings-as-errors') || $warnings === [])
            ? self::SUCCESS
            : self::FAILURE;
    }

    /** @return list<string> */
    private function sourceRoots(): array
    {
        return [
            resource_path('views'),
            resource_path('js'),
            app_path(),
            config_path(),
            lang_path(),
            base_path('routes'),
            database_path('seeders'),
        ];
    }

    private function isScannable(string $filename, string $extension): bool
    {
        return str_ends_with($filename, '.blade.php') || in_array($extension, self::EXTENSIONS, true);
    }

    private function withoutNonRenderedComments(string $source): string
    {
        return (string) preg_replace_callback(
            '/\{\{--.*?--\}\}|<!--.*?-->|\/\*.*?\*\//su',
            static fn (array $match): string => str_repeat("\n", substr_count($match[0], "\n")),
            $source,
        );
    }

    private function isAllowed(string $rule, string $path, string $line): bool
    {
        if ($rule === 'legacy_brand') {
            // Barcode prefixes are machine identifiers, not rendered branding.
            // Keep them configurable without teaching this copy audit to treat
            // an operational code as a public-facing university name.
            if (preg_match('/barcode(?:_[a-z0-9]+)*_prefix|barcodePrefix/i', $line) === 1) {
                return true;
            }
            if (preg_match('/(?:[\w.+-]+@)?[\w.-]*(?:kazutb|kaztbu)[\w.-]*(?:\.edu\.kz|\.local)/iu', $line) === 1
                || preg_match('/[\x27\x22](?:kazutb|kaztbu)-[a-z0-9_-]+[\x27\x22]\s*=>/iu', $line) === 1) {
                return true;
            }
            // A verified social-media URL/handle is an external machine
            // identifier, not a prose form of the university brand. Keep the
            // exception limited to the explicit contact payload fields.
            if (preg_match('/instagram_(?:url|handle)/iu', $line) === 1) {
                return true;
            }
            if (str_contains($path, 'LibraryAuthenticator.php')
                || str_contains($path, 'AuditLibraryI18n.php')
                || str_contains($path, 'SmokeLibraryRoles.php')
                || str_contains($line, 'User-Agent')) {
                return true;
            }
            if (str_contains($path, 'database/seeders/') && preg_match('/(?:title|publisher|name|isbn|issn|doi|legacy|external_id)/iu', $line) === 1) {
                return true;
            }
            if ($path === 'config/external_resources.php' && str_contains($line, "'slug' =>")) {
                return true;
            }
        }

        if ($rule === 'developer_copy' && $path === 'app/Console/Commands/ApplyMarcRecovery.php') {
            // This command persists recovery provenance and internal field
            // names; none of these strings is rendered in the application UI.
            return true;
        }

        if ($rule === 'placeholder_copy' && (str_contains($line, 'placeholder=') || str_contains($line, "'placeholder'"))) {
            return true;
        }

        return false;
    }

    /** @return array{severity:string,rule:string,path:string,line:int,excerpt:string} */
    private function finding(string $severity, string $rule, string $path, int $line, string $text): array
    {
        return [
            'severity' => $severity,
            'rule' => $rule,
            'path' => $path,
            'line' => $line,
            'excerpt' => trim($text),
        ];
    }
}
