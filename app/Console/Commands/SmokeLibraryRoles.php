<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\LocaleResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class SmokeLibraryRoles extends Command
{
    protected $signature = 'library:smoke-roles
        {--base-url= : Running application URL}
        {--locales=kk,ru,en : Comma-separated locale allowlist}
        {--json= : Optional JSON result path}';

    protected $description = 'HTTP smoke of every demo role, locale and visible navigation link';

    /** @var list<array<string, mixed>> */
    private array $results = [];

    public function handle(): int
    {
        $baseUrl = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/');
        if (! preg_match('#^https?://#i', $baseUrl)) {
            $this->error('A valid http(s) --base-url is required.');

            return self::FAILURE;
        }

        $locales = array_values(array_unique(array_filter(array_map(
            static fn (string $locale): string => mb_strtolower(trim($locale)),
            explode(',', (string) $this->option('locales')),
        ))));
        if ($locales === [] || array_diff($locales, LocaleResolver::SUPPORTED) !== []) {
            $this->error('Locales must be a comma-separated subset of kk,ru,en.');

            return self::FAILURE;
        }

        $logErrorsBefore = $this->logErrorCount();
        $failed = false;

        foreach ($locales as $locale) {
            foreach (['/dashboard', '/librarian', '/admin'] as $path) {
                $result = $this->request(new Client(['http_errors' => false, 'allow_redirects' => false]), 'guest', $locale, $baseUrl, $path.'?lang='.$locale);
                $this->results[] = $result;
                $failed = $failed || $result['status'] !== 302;
            }
        }

        foreach ((array) config('demo_users.identities', []) as $slug => $identity) {
            $email = (string) ($identity['email'] ?? '');
            $landing = (string) ($identity['landing'] ?? '');
            if ($email === '' || $landing === '' || ! User::query()->where('email', $email)->exists()) {
                $this->results[] = ['role' => $slug, 'url' => $landing, 'status' => 0, 'duration_ms' => 0, 'redirect' => null, 'exception' => 'demo_user_missing'];
                $failed = true;

                continue;
            }

            $user = User::query()->where('email', $email)->firstOrFail();
            $originalLocale = $user->locale;
            try {
                foreach ($locales as $locale) {
                    $jar = new CookieJar;
                    $client = new Client(['cookies' => $jar, 'http_errors' => false, 'allow_redirects' => false, 'timeout' => 20]);

                    $login = $client->get($baseUrl.'/login?lang='.$locale);
                    preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/i', (string) $login->getBody(), $token);
                    if (($token[1] ?? '') === '') {
                        throw new \RuntimeException('csrf_token_missing');
                    }
                    $csrf = html_entity_decode($token[1], ENT_QUOTES);
                    $response = $client->post($baseUrl.'/login/demo/'.rawurlencode((string) $slug), [
                        'form_params' => ['_token' => $csrf],
                    ]);
                    if ($response->getStatusCode() !== 302) {
                        throw new \RuntimeException('demo_login_status_'.$response->getStatusCode());
                    }
                    $authenticatedPage = $client->get($baseUrl.$landing);
                    preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/i', (string) $authenticatedPage->getBody(), $authenticatedToken);
                    if (($authenticatedToken[1] ?? '') === '') {
                        throw new \RuntimeException('authenticated_csrf_token_missing');
                    }
                    $csrf = html_entity_decode($authenticatedToken[1], ENT_QUOTES);
                    $localeResponse = $client->post($baseUrl.'/locale', [
                        'form_params' => ['_token' => $csrf, 'locale' => $locale, 'return_to' => $landing],
                    ]);
                    if ($localeResponse->getStatusCode() !== 302) {
                        throw new \RuntimeException('locale_switch_status_'.$localeResponse->getStatusCode());
                    }

                    $paths = $this->requiredPaths((string) $slug, $landing);
                    $landingResult = $this->request($client, (string) $slug, $locale, $baseUrl, $landing);
                    $this->results[] = $landingResult;
                    $failed = $failed || $this->failedAllowedPage($landingResult);

                    foreach ($this->visibleRolePaths((string) ($landingResult['body'] ?? ''), $baseUrl) as $path) {
                        $paths[] = $path;
                    }

                    foreach (array_values(array_unique($paths)) as $path) {
                        if ($path === $landing) {
                            continue;
                        }
                        $result = $this->request($client, (string) $slug, $locale, $baseUrl, $path);
                        $this->results[] = $result;
                        $failed = $failed || $this->failedAllowedPage($result);
                    }
                }
            } catch (Throwable $exception) {
                $this->results[] = ['role' => $slug, 'locale' => $locale ?? '?', 'url' => $landing, 'status' => 0, 'duration_ms' => 0, 'redirect' => null, 'exception' => $exception::class.': '.$exception->getMessage()];
                $failed = true;
            } finally {
                $user->forceFill(['locale' => $originalLocale])->save();
            }
        }

        $logErrorsAfter = $this->logErrorCount();
        if ($logErrorsAfter > $logErrorsBefore) {
            $failed = true;
        }

        $rows = array_map(static fn (array $result): array => [
            $result['role'], $result['locale'] ?? '', $result['url'], $result['status'], $result['html_lang'] ?? '',
            $result['duration_ms'], $result['redirect'] ?? '', $result['exception'] ?? '',
        ], $this->results);
        $this->table(['Role', 'Locale', 'URL', 'Status', 'HTML lang', 'ms', 'Redirect', 'Exception'], $rows);
        $this->line(sprintf('requests=%d http_500=%d new_log_errors=%d', count($this->results), count(array_filter($this->results, static fn (array $row): bool => ($row['status'] ?? 0) >= 500)), max(0, $logErrorsAfter - $logErrorsBefore)));

        if (is_string($this->option('json')) && $this->option('json') !== '') {
            File::put((string) $this->option('json'), json_encode([
                'base_url' => $baseUrl,
                'generated_at' => now('UTC')->toIso8601String(),
                'new_log_errors' => max(0, $logErrorsAfter - $logErrorsBefore),
                'results' => array_map(static function (array $row): array {
                    unset($row['body']);

                    return $row;
                }, $this->results),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function request(Client $client, string $role, string $locale, string $baseUrl, string $path): array
    {
        $started = hrtime(true);
        try {
            $response = $client->get($baseUrl.$path);
            $body = (string) $response->getBody();
            $status = $response->getStatusCode();
            preg_match('/<html\b[^>]*\blang=["\']([^"\']+)["\']/i', $body, $htmlLang);
            preg_match('/<title>(.*?)<\/title>/is', $body, $title);
            $visibleText = strip_tags((string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $body));
            preg_match('/\b(?:admin|brand|common|errors|librarian|member|reservation|roles|shell)\.[a-z0-9_.]+\b/', $visibleText, $rawKey);
            $mixed = $this->mixedShellText($visibleText, $locale);
            $exception = preg_match('/SQLSTATE|Stack trace|Whoops, looks like something went wrong/i', $body)
                ? 'generic_or_debug_error_page'
                : (($rawKey[0] ?? null) ? 'raw_translation_key:'.$rawKey[0] : ($mixed ? 'mixed_interface:'.$mixed : null));
            $legacyBrand = 'Kaz'.'UTB|Kaz'.'TBU|Каз'.'УТБ|Каз'.'ТБУ|Қаз'.'УТБ|Қаз'.'ТБУ';
            // Catalogue metadata and external-resource titles may legitimately
            // preserve historical names. Branding drift is therefore checked
            // in the browser title; source copy is covered separately by the
            // UI-copy audit.
            $brandText = strip_tags($title[1] ?? '');
            $brandText = (string) preg_replace(
                '/(?:[\w.+-]+@)?[\w.-]*(?:kazutb|kaztbu)[\w.-]*(?:\.edu\.kz|\.local)/iu',
                '',
                $brandText,
            );
            if (preg_match('/(?<![\pL\pN])(?:'.$legacyBrand.')(?![\pL\pN])/iu', $brandText) === 1) {
                $exception = 'prohibited_brand';
            } elseif (preg_match('/(?:§\s*\d|пункт\s+ТЗ|раздел\s+ДИР)|[¶●◉▪▫◆◇▶►⇒✓✔✕❌👉📌⚠🔎📊⚖🧩📈🧾]/iu', $visibleText) === 1) {
                $exception = 'prohibited_ui_copy';
            }

            return [
                'role' => $role,
                'locale' => $locale,
                'url' => $path,
                'status' => $status,
                'html_lang' => $htmlLang[1] ?? null,
                'title' => trim(html_entity_decode(strip_tags($title[1] ?? ''), ENT_QUOTES)),
                'duration_ms' => round((hrtime(true) - $started) / 1_000_000, 1),
                'redirect' => $response->getHeaderLine('Location') ?: null,
                'exception' => $exception,
                'body' => $body,
            ];
        } catch (Throwable $exception) {
            return [
                'role' => $role, 'locale' => $locale, 'url' => $path, 'status' => 0,
                'duration_ms' => round((hrtime(true) - $started) / 1_000_000, 1),
                'redirect' => null, 'exception' => $exception::class.': '.$exception->getMessage(), 'body' => '',
            ];
        }
    }

    /** @return list<string> */
    private function requiredPaths(string $slug, string $landing): array
    {
        if ($slug === 'student') {
            return [
                '/dashboard', '/dashboard/loans', '/dashboard/reservations', '/dashboard/history',
                '/dashboard/fines', '/dashboard/incidents', '/dashboard/notifications',
                '/dashboard/digital-materials', '/dashboard/collections', '/dashboard/messages',
                '/dashboard/card', '/dashboard/profile', '/dashboard/search',
            ];
        }

        return [$landing];
    }

    /** @return list<string> */
    private function visibleRolePaths(string $html, string $baseUrl): array
    {
        preg_match_all('/<a\b[^>]*href="([^"]+)"/i', $html, $matches);
        $base = parse_url($baseUrl);
        $paths = [];
        foreach ($matches[1] ?? [] as $href) {
            $href = html_entity_decode((string) $href, ENT_QUOTES);
            if (str_starts_with($href, '/')) {
                $path = parse_url($href, PHP_URL_PATH) ?: '';
                $query = parse_url($href, PHP_URL_QUERY);
            } else {
                $parts = parse_url($href);
                if (($parts['host'] ?? null) !== ($base['host'] ?? null) || ($parts['port'] ?? null) !== ($base['port'] ?? null)) {
                    continue;
                }
                $path = $parts['path'] ?? '';
                $query = $parts['query'] ?? null;
            }
            if (! preg_match('#^/(dashboard|librarian|admin)(?:/|$)#', $path) || str_contains($path, '/export')) {
                continue;
            }
            $paths[] = $path.($query ? '?'.$query : '');
        }

        return array_values(array_unique($paths));
    }

    /** @param array<string, mixed> $result */
    private function failedAllowedPage(array $result): bool
    {
        return ($result['status'] ?? 0) !== 200
            || ($result['html_lang'] ?? null) !== ($result['locale'] ?? null)
            || trim((string) ($result['title'] ?? '')) === ''
            || ($result['exception'] ?? null) !== null;
    }

    private function mixedShellText(string $text, string $locale): ?string
    {
        $phrases = match ($locale) {
            'ru' => ['My Library', 'Your personal reading workspace', 'Browse Catalog', 'Sign out', 'Operations', 'Librarian Console'],
            'kk' => ['My Library', 'Your personal reading workspace', 'Browse Catalog', 'Sign out', 'Моя библиотека', 'Операции', 'Кабинет библиотекаря'],
            'en' => ['Моя библиотека', 'Операции', 'Кабинет библиотекаря', 'Менің кітапханам', 'Операциялар', 'Кітапханашы кабинеті'],
            default => [],
        };
        foreach ($phrases as $phrase) {
            if (str_contains($text, $phrase)) {
                return $phrase;
            }
        }

        return null;
    }

    private function logErrorCount(): int
    {
        $path = storage_path('logs/laravel.log');
        if (! is_readable($path)) {
            return 0;
        }

        return preg_match_all('/production\.(?:ERROR|CRITICAL)/', (string) file_get_contents($path)) ?: 0;
    }
}
