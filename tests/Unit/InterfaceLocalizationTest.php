<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Lang;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class InterfaceLocalizationTest extends TestCase
{
    public function test_literal_translation_keys_used_by_views_exist_in_every_supported_locale(): void
    {
        $missing = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all('/(?:__|trans)\(\s*[\'\"]([^\'\"]+)[\'\"]\s*[,)]/', $contents ?: '', $matches);

            foreach (array_unique($matches[1] ?? []) as $key) {
                foreach (['ru', 'kk', 'en'] as $locale) {
                    if (! Lang::has($key, $locale, false)) {
                        $missing[] = sprintf('%s: %s (%s)', $locale, $key, $file->getFilename());
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), implode("\n", array_unique($missing)));
    }

    public function test_data_quality_issue_page_does_not_expose_stored_foreign_language_guidance(): void
    {
        $view = file_get_contents(resource_path('views/librarian/data-quality/show.blade.php'));

        $this->assertIsString($view);
        $this->assertStringNotContainsString('$issue->suggested_action', $view);
        $this->assertStringNotContainsString('$issue->expected_format', $view);
        $this->assertStringNotContainsString('$issue->message', $view);
    }
}
