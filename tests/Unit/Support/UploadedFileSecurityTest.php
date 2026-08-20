<?php

namespace Tests\Unit\Support;

use App\Support\UploadedFileSecurity;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadedFileSecurityTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function executableExtensions(): array
    {
        return collect(['php', 'phtml', 'phar', 'sh', 'exe'])
            ->mapWithKeys(fn (string $extension): array => [$extension => [$extension]])
            ->all();
    }

    #[DataProvider('executableExtensions')]
    public function test_executable_original_extensions_are_rejected(string $extension): void
    {
        $file = UploadedFile::fake()->createWithContent('payload.'.$extension, 'synthetic-content');

        $this->expectException(ValidationException::class);
        UploadedFileSecurity::assertSafe($file);
    }

    public function test_active_pdf_actions_are_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'active.pdf',
            "%PDF-1.4\n1 0 obj << /OpenAction << /S /JavaScript /JS (synthetic) >> >>\nendobj\n%%EOF",
        );

        $this->expectException(ValidationException::class);
        UploadedFileSecurity::assertPassivePdf($file);
    }

    public function test_fake_pdf_content_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('fake.pdf', 'not-a-pdf');

        $this->expectException(ValidationException::class);
        UploadedFileSecurity::assertPassivePdf($file);
    }

    public function test_passive_pdf_is_accepted(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'passive.pdf',
            "%PDF-1.4\n1 0 obj << /Type /Catalog >>\nendobj\n%%EOF",
        );

        UploadedFileSecurity::assertPassivePdf($file);
        $this->addToAssertionCount(1);
    }
}
