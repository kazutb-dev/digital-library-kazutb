<?php

namespace App\Console\Commands;

use App\Support\LocaleResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditLibraryI18n extends Command
{
    protected $signature = 'library:i18n:audit {--json= : Optional JSON report path}';

    protected $description = 'Audit translation parity, placeholders and prohibited UI branding';

    /** @var list<string> */
    private const INTERNATIONAL_TERMS = [
        'API', 'CSV', 'DOI', 'Excel', 'ISBN', 'ISSN', 'MARC',
        'ORCID', 'PDF', 'QR', 'URL', 'English', 'UTC', 'ID', 'RDR00000001',
        'ҚАЗ', 'РУС', 'ENG', 'Қазақша', 'Русский',
    ];

    public function handle(): int
    {
        $translations = [];
        foreach (LocaleResolver::SUPPORTED as $locale) {
            foreach (File::files(lang_path($locale)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $group = $file->getFilenameWithoutExtension();
                $values = (static fn (string $path): mixed => require $path)($file->getPathname());
                $translations[$locale][$group] = $this->flatten(is_array($values) ? $values : []);
            }
        }

        $problems = [];
        $allGroups = collect($translations)->flatMap(fn (array $groups): array => array_keys($groups))->unique()->sort();
        foreach ($allGroups as $group) {
            $allKeys = collect(LocaleResolver::SUPPORTED)
                ->flatMap(fn (string $locale): array => array_keys($translations[$locale][$group] ?? []))
                ->unique()
                ->sort();

            foreach ($allKeys as $key) {
                $values = [];
                foreach (LocaleResolver::SUPPORTED as $locale) {
                    $value = $translations[$locale][$group][$key] ?? null;
                    $values[$locale] = $value;
                    if ($value === null) {
                        $problems[] = $this->problem($group.'.'.$key, $values, 'critical: missing '.$locale);
                    } elseif (! is_string($value) && ! is_numeric($value)) {
                        $problems[] = $this->problem($group.'.'.$key, $values, 'critical: non-scalar '.$locale);
                    } elseif (trim((string) $value) === '') {
                        $problems[] = $this->problem($group.'.'.$key, $values, 'critical: empty '.$locale);
                    } elseif ((string) $value === $group.'.'.$key) {
                        $problems[] = $this->problem($group.'.'.$key, $values, 'critical: raw key value '.$locale);
                    } elseif ($this->containsProhibitedBrand((string) $value)) {
                        $problems[] = $this->problem($group.'.'.$key, $values, 'critical: prohibited brand '.$locale);
                    }
                }

                $placeholderSets = [];
                foreach ($values as $locale => $value) {
                    preg_match_all('/(?<![\/\w]):([A-Za-z_][A-Za-z0-9_]*)/', (string) $value, $matches);
                    $placeholderSets[$locale] = array_values(array_unique($matches[1] ?? []));
                    sort($placeholderSets[$locale]);
                }
                if (count(array_unique(array_map('serialize', $placeholderSets))) > 1) {
                    $problems[] = $this->problem($group.'.'.$key, $values, 'critical: placeholder mismatch');
                }

                $scalarValues = array_map(static fn (mixed $value): string => trim((string) $value), $values);
                if (count(array_unique($scalarValues)) === 1
                    && $scalarValues['kk'] !== ''
                    && ! in_array($scalarValues['kk'], self::INTERNATIONAL_TERMS, true)
                    && ! preg_match('/^[\d\W_]+$/u', $scalarValues['kk'])) {
                    $problems[] = $this->problem($group.'.'.$key, $values, 'warning: identical kk/ru/en');
                }
            }
        }

        $critical = array_values(array_filter($problems, fn (array $row): bool => str_starts_with($row['problem'], 'critical:')));
        $warnings = array_values(array_filter($problems, fn (array $row): bool => str_starts_with($row['problem'], 'warning:')));
        $counts = [];
        foreach (LocaleResolver::SUPPORTED as $locale) {
            $counts[$locale] = collect($translations[$locale] ?? [])->sum(fn (array $keys): int => count($keys));
        }

        $this->table(
            ['Key', 'KK', 'RU', 'EN', 'Problem'],
            array_map(fn (array $row): array => [
                $row['key'],
                $this->short($row['kk']),
                $this->short($row['ru']),
                $this->short($row['en']),
                $row['problem'],
            ], array_slice($problems, 0, 100)),
        );
        $this->line(sprintf(
            'kk=%d ru=%d en=%d critical=%d warnings=%d allowlist=%s',
            $counts['kk'], $counts['ru'], $counts['en'], count($critical), count($warnings), implode(',', self::INTERNATIONAL_TERMS),
        ));

        if (is_string($this->option('json')) && $this->option('json') !== '') {
            File::put((string) $this->option('json'), json_encode([
                'generated_at' => now('UTC')->toIso8601String(),
                'locales' => LocaleResolver::SUPPORTED,
                'counts' => $counts,
                'critical_count' => count($critical),
                'warning_count' => count($warnings),
                'allowlist' => self::INTERNATIONAL_TERMS,
                'problems' => $problems,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        }

        return $critical === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed> */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $flat = array_merge($flat, $this->flatten($value, $path));
            } else {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }

    /** @param array<string, mixed> $values */
    private function problem(string $key, array $values, string $problem): array
    {
        return [
            'key' => $key,
            'kk' => $values['kk'] ?? null,
            'ru' => $values['ru'] ?? null,
            'en' => $values['en'] ?? null,
            'problem' => $problem,
        ];
    }

    /** @return list<string> */
    private function prohibitedBrandLocations(): array
    {
        $locations = [];
        foreach ([app_path(), resource_path('views'), resource_path('js'), base_path('routes'), database_path('seeders'), lang_path()] as $root) {
            foreach (File::allFiles($root) as $file) {
                if (! in_array($file->getExtension(), ['php', 'blade.php', 'js', 'jsx', 'ts', 'tsx'], true)
                    && ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }
                foreach (preg_split('/\R/', File::get($file->getPathname())) ?: [] as $line => $text) {
                    if ($this->containsProhibitedBrand($text)) {
                        $locations[] = str_replace(base_path().'/', '', $file->getPathname()).':'.($line + 1);
                    }
                }
            }
        }

        return $locations;
    }

    private function containsProhibitedBrand(string $value): bool
    {
        $value = (string) preg_replace(
            '/(?:[\w.+-]+@)?[\w.-]*(?:kazutb|kaztbu)[\w.-]*(?:\.edu\.kz|\.local)/iu',
            '',
            $value,
        );
        $latin = 'Kaz'.'UTB|Kaz'.'TBU';
        $cyrillic = 'Каз'.'УТБ|Каз'.'ТБУ|Қаз'.'УТБ|Қаз'.'ТБУ';

        return preg_match('/(?<![\pL\pN])(?:'.$latin.'|'.$cyrillic.')(?![\pL\pN])/iu', $value) === 1;
    }

    private function short(mixed $value): string
    {
        return mb_strimwidth((string) ($value ?? '∅'), 0, 44, '…');
    }
}
