<?php

namespace Tests\Feature;

use Tests\TestCase;

class UiCopyAuditTest extends TestCase
{
    public function test_runtime_user_copy_passes_the_canonical_audit(): void
    {
        $this->artisan('library:ui-copy-audit')
            ->expectsOutputToContain('critical=0 warnings=0')
            ->assertExitCode(0);
    }

    public function test_official_branding_config_contains_only_full_names(): void
    {
        $names = config('library_branding.university');

        $this->assertSame('Қ. Құлажанов атындағы Қазақ технология және бизнес университеті', $names['kk']);
        $this->assertSame('Казахский университет технологии и бизнеса имени К. Кулажанова', $names['ru']);
        $this->assertSame('Kazakh University of Technology and Business named after K. Kulazhanov', $names['en']);
    }
}
