<?php

namespace Database\Seeders;

use App\Models\Catalog\RepositoryApproval;
use App\Models\Catalog\RepositoryAuthor;
use App\Models\Catalog\RepositoryItem;
use App\Models\Catalog\RepositoryItemVersion;
use App\Models\Catalog\RepositoryReview;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Demonstration-only content for the institutional repository.
 *
 * Every title, author and PDF is explicitly marked as demo content so the
 * seed data can never be mistaken for an actual university scholarly work. The
 * seeder follows DemoUserSeeder's production boundary and does nothing when
 * demo login is disabled.
 */
class RepositorySeeder extends Seeder
{
    public function run(): void
    {
        if (! (bool) config('demo_users.enabled')) {
            $this->command?->warn('Demo login is off — repository demo records not seeded.');

            return;
        }

        $uploader = User::query()->where('email', 'demo-librarian@kazutb.local')->first();
        $director = User::query()->where('email', 'demo-director@kazutb.local')->first();

        if ($uploader === null || $director === null) {
            $this->command?->warn('Demo librarian/director missing — repository demo records not seeded.');

            return;
        }

        foreach ($this->records() as $position => $record) {
            $pdf = $this->demoPdf($record['work_type']);
            $path = sprintf('repository/demo/%s.pdf', str_replace('_', '-', $record['work_type']));

            if (! Storage::disk('local')->put($path, $pdf)) {
                throw new \RuntimeException("Unable to write repository demo PDF: {$path}");
            }

            DB::transaction(function () use ($record, $position, $path, $pdf, $uploader, $director): void {
                $checksum = hash('sha256', $pdf);
                $item = RepositoryItem::query()->updateOrCreate(
                    ['public_id' => sprintf('15000000-0000-4000-8000-%012d', $position + 1)],
                    [
                        'title' => $record['title'],
                        'authors' => [$record['author']],
                        'work_type' => $record['work_type'],
                        'year' => 2026,
                        'department' => 'Демонстрационный фонд библиотеки',
                        'udc_code' => $record['udc'],
                        'abstract' => 'ДЕМО-ЗАПИСЬ. Вымышленный материал создан только для проверки функций научного репозитория; это не реальная научная работа.',
                        'keywords' => ['демо', 'тестовые данные', 'научный репозиторий'],
                        'language' => 'ru',
                        'file_path' => $path,
                        'file_name' => 'DEMO-'.$record['work_type'].'.pdf',
                        'file_size' => strlen($pdf),
                        'checksum_sha256' => $checksum,
                        'version_number' => 1,
                        'status' => 'published',
                        'uploaded_by' => $uploader->getKey(),
                        'reviewed_by' => $uploader->getKey(),
                        'approved_by' => $director->getKey(),
                        'published_at' => now('UTC')->subDays(7 - $position),
                        'university' => 'Казахский университет технологии и бизнеса имени К. Кулажанова — демонстрационные данные',
                        'source' => 'Демонстрационный набор данных; не является реальной публикацией.',
                        'rights_holder' => 'Демонстрационные данные научной библиотеки',
                        'copyright_status' => 'university_owned',
                        'licence_type' => 'demo-open-access',
                        'access_policy' => 'full_public',
                        'embargo_until' => null,
                        'post_embargo_access_policy' => null,
                    ],
                );

                $item->authorsList()->delete();
                RepositoryAuthor::query()->create([
                    'repository_item_id' => $item->getKey(),
                    'display_name' => $record['author'],
                    'normalised_name' => mb_strtolower($record['author']),
                    'affiliation' => 'Демонстрационные данные научной библиотеки',
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);

                $version = RepositoryItemVersion::query()->updateOrCreate(
                    ['repository_item_id' => $item->getKey(), 'version_number' => 1],
                    [
                        'storage_disk' => 'local',
                        'file_path' => $path,
                        'file_name' => 'DEMO-'.$record['work_type'].'.pdf',
                        'file_size' => strlen($pdf),
                        'mime_type' => 'application/pdf',
                        'checksum_sha256' => $checksum,
                        'change_reason' => 'Создание явно помеченного демонстрационного материала.',
                        'created_by' => $uploader->getKey(),
                        'is_active' => true,
                    ],
                );

                foreach ([
                    ['from' => 'quality_review', 'to' => 'pending_approval', 'reviewer' => $uploader],
                    ['from' => 'pending_approval', 'to' => 'approved', 'reviewer' => $director],
                    ['from' => 'approved', 'to' => 'published', 'reviewer' => $director],
                ] as $review) {
                    RepositoryReview::query()->updateOrCreate(
                        [
                            'repository_item_id' => $item->getKey(),
                            'review_type' => $review['from'],
                            'decision' => $review['to'],
                            'reviewer_id' => $review['reviewer']->getKey(),
                        ],
                        ['comment' => 'ДЕМО: история workflow создана сидером и не является реальным решением.'],
                    );
                }

                $item->refresh();
                $fingerprint = $item->approvalFingerprint($version);
                $approval = RepositoryApproval::query()
                    ->where('repository_item_id', $item->getKey())
                    ->where('repository_item_version_id', $version->getKey())
                    ->where('approver_id', $director->getKey())
                    ->where('approver_role_snapshot', 'director')
                    ->where('checksum_sha256', $checksum)
                    ->where('metadata_fingerprint', $fingerprint)
                    ->first();
                $approval ??= RepositoryApproval::query()->create([
                    'repository_item_id' => $item->getKey(),
                    'repository_item_version_id' => $version->getKey(),
                    'approver_id' => $director->getKey(),
                    'approver_role_snapshot' => 'director',
                    'checksum_sha256' => $checksum,
                    'metadata_fingerprint' => $fingerprint,
                    'approved_at' => now('UTC'),
                ]);

                // Intentional seeder-only finalisation after creating matching
                // immutable evidence. Normal application publication goes
                // exclusively through RepositoryWorkflow.
                DB::table('repository_items')->where('id', $item->getKey())->update([
                    'status' => 'published',
                    'reviewed_by' => $uploader->getKey(),
                    'approved_by' => $director->getKey(),
                    'active_approval_id' => $approval->getKey(),
                    'published_at' => now('UTC')->subDays(7 - $position),
                    'updated_at' => now('UTC'),
                ]);
            });
        }

        $this->command?->info('Repository: 7 explicitly labelled demo works with private PDFs seeded.');
    }

    /** @return list<array{work_type: string, title: string, author: string, udc: string}> */
    private function records(): array
    {
        return [
            ['work_type' => 'bachelor_thesis', 'title' => '[ДЕМО] Дипломная работа: навигация по цифровой библиотеке', 'author' => 'Демо-автор Бакалавр', 'udc' => '004.9'],
            ['work_type' => 'master_thesis', 'title' => '[ДЕМО] Магистерская диссертация: открытые научные данные', 'author' => 'Демо-автор Магистр', 'udc' => '001.8'],
            ['work_type' => 'phd_dissertation', 'title' => '[ДЕМО] PhD-диссертация: сохранность научного наследия', 'author' => 'Демо-автор PhD', 'udc' => '001.89'],
            ['work_type' => 'scientific_article', 'title' => '[ДЕМО] Научная статья: качество метаданных репозитория', 'author' => 'Демо-автор Исследователь', 'udc' => '02:004'],
            ['work_type' => 'research_report', 'title' => '[ДЕМО] Научный отчёт: использование электронных коллекций', 'author' => 'Демо-исследовательская группа', 'udc' => '025.4'],
            ['work_type' => 'university_publication', 'title' => '[ДЕМО] Университетская публикация: гид по открытой науке', 'author' => 'Демо-редакция университета', 'udc' => '001.92'],
            ['work_type' => 'thesis_abstract', 'title' => '[ДЕМО] Автореферат: модель институционального репозитория', 'author' => 'Демо-автор Автореферат', 'udc' => '378.4'],
        ];
    }

    private function demoPdf(string $workType): string
    {
        $message = "DEMO ONLY - fictional repository record - {$workType}";
        $stream = "BT\n/F1 16 Tf\n72 760 Td\n({$message}) Tj\n0 -28 Td\n/F1 11 Tf\n(Not a real scholarly work. Generated for functional testing.) Tj\nET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
