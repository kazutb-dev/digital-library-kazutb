<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class ShortlistExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function mixedSession(): array
    {
        return [
            'library.shortlist' => [
                'isbn-001' => [
                    'identifier' => 'isbn-001',
                    'title' => 'Основы программирования',
                    'type' => 'book',
                    'author' => 'Иванов А.А.',
                    'publisher' => 'Наука',
                    'year' => '2023',
                    'isbn' => '978-5-000-00001-0',
                    'language' => 'ru',
                    'addedAt' => '2024-01-01T00:00:00Z',
                ],
                'isbn-002' => [
                    'identifier' => 'isbn-002',
                    'title' => 'Data Structures',
                    'type' => 'book',
                    'author' => 'Smith J.',
                    'publisher' => 'O\'Reilly',
                    'year' => '2022',
                    'isbn' => '978-0-000-00002-0',
                    'language' => 'en',
                    'addedAt' => '2024-01-02T00:00:00Z',
                ],
                'ext:ipr-smart' => [
                    'identifier' => 'ext:ipr-smart',
                    'title' => 'IPR SMART',
                    'type' => 'external_resource',
                    'provider' => 'IPR Media',
                    'url' => 'https://www.iprbookshop.ru/',
                    'access_type' => 'remote_auth',
                    'addedAt' => '2024-01-03T00:00:00Z',
                ],
            ],
        ];
    }

    public function test_export_returns_numbered_format(): void
    {
        $response = $this->withSession($this->mixedSession())
            ->getJson('/api/v1/shortlist/export?format=numbered');

        $response
            ->assertOk()
            ->assertJsonPath('data.format', 'numbered')
            ->assertJsonPath('data.count', 3)
            ->assertJsonPath('meta.total', 3);

        $text = $response->json('data.text');
        $this->assertStringContainsString('1.', $text);
        $this->assertStringContainsString('Иванов А.А.', $text);
        $this->assertStringContainsString('IPR SMART', $text);
    }

    public function test_export_returns_grouped_format(): void
    {
        $response = $this->withSession($this->mixedSession())
            ->getJson('/api/v1/shortlist/export?format=grouped');

        $response
            ->assertOk()
            ->assertJsonPath('data.format', 'grouped')
            ->assertJsonPath('data.count', 3)
            ->assertJsonPath('data.sections.books', 2)
            ->assertJsonPath('data.sections.external', 1);

        $text = $response->json('data.text');
        $this->assertStringContainsString('Основная литература', $text);
        $this->assertStringContainsString('Электронные ресурсы', $text);
    }

    public function test_export_returns_syllabus_format(): void
    {
        $response = $this->withSession($this->mixedSession())
            ->getJson('/api/v1/shortlist/export?format=syllabus');

        $response
            ->assertOk()
            ->assertJsonPath('data.format', 'syllabus')
            ->assertJsonPath('data.count', 3);

        $text = $response->json('data.text');
        $this->assertStringContainsString('СПИСОК ЛИТЕРАТУРЫ', $text);
        $this->assertStringContainsString('Основная литература:', $text);
        $this->assertStringContainsString('Электронные ресурсы:', $text);
        $this->assertStringContainsString('рус.', $text);
        $this->assertStringContainsString('англ.', $text);
        $this->assertStringContainsString('доступ по авторизации', $text);
    }

    public function test_export_defaults_to_numbered_for_unknown_format(): void
    {
        $response = $this->withSession($this->mixedSession())
            ->getJson('/api/v1/shortlist/export?format=invalid');

        $response
            ->assertOk()
            ->assertJsonPath('data.format', 'numbered');
    }

    public function test_export_defaults_to_numbered_without_format_param(): void
    {
        $response = $this->withSession($this->mixedSession())
            ->getJson('/api/v1/shortlist/export');

        $response
            ->assertOk()
            ->assertJsonPath('data.format', 'numbered')
            ->assertJsonPath('data.count', 3);
    }

    public function test_export_empty_shortlist(): void
    {
        $response = $this->withSession([])
            ->getJson('/api/v1/shortlist/export?format=grouped');

        $response
            ->assertOk()
            ->assertJsonPath('data.text', '')
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_export_books_only_no_external_section(): void
    {
        $session = [
            'library.shortlist' => [
                'isbn-1' => [
                    'identifier' => 'isbn-1',
                    'title' => 'Книга 1',
                    'type' => 'book',
                    'author' => 'Автор 1',
                    'year' => '2020',
                    'addedAt' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        $response = $this->withSession($session)
            ->getJson('/api/v1/shortlist/export?format=grouped');

        $response->assertOk();

        $text = $response->json('data.text');
        $this->assertStringContainsString('Основная литература', $text);
        $this->assertStringNotContainsString('Электронные ресурсы', $text);
        $this->assertArrayNotHasKey('external', $response->json('data.sections'));
    }

    public function test_export_external_only_no_books_section(): void
    {
        $session = [
            'library.shortlist' => [
                'ext:test' => [
                    'identifier' => 'ext:test',
                    'title' => 'Test Resource',
                    'type' => 'external_resource',
                    'provider' => 'TestProvider',
                    'url' => 'https://example.com',
                    'addedAt' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        $response = $this->withSession($session)
            ->getJson('/api/v1/shortlist/export?format=grouped');

        $response->assertOk();

        $text = $response->json('data.text');
        $this->assertStringNotContainsString('Основная литература', $text);
        $this->assertStringContainsString('Электронные ресурсы', $text);
    }

    public function test_export_numbered_includes_isbn(): void
    {
        $response = $this->withSession($this->mixedSession())
            ->getJson('/api/v1/shortlist/export?format=numbered');

        $text = $response->json('data.text');
        $this->assertStringContainsString('ISBN 978-5-000-00001-0', $text);
    }

    public function test_export_numbered_includes_external_url(): void
    {
        $response = $this->withSession($this->mixedSession())
            ->getJson('/api/v1/shortlist/export?format=numbered');

        $text = $response->json('data.text');
        $this->assertStringContainsString('https://www.iprbookshop.ru/', $text);
        $this->assertStringContainsString('[Электронный ресурс]', $text);
    }

    public function test_export_preserves_stable_ordering(): void
    {
        $response = $this->withSession($this->mixedSession())
            ->getJson('/api/v1/shortlist/export?format=numbered');

        $text = $response->json('data.text');
        $lines = array_filter(explode("\n", $text));

        $this->assertStringContainsString('Основы программирования', $lines[0]);
        $this->assertStringContainsString('Data Structures', $lines[1]);
        $this->assertStringContainsString('IPR SMART', $lines[2]);
    }
}
