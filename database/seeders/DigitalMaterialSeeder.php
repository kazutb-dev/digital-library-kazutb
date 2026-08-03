<?php

namespace Database\Seeders;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\ElectronicMaterial;
use Database\Seeders\Support\DemoPdfBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Attaches readable demo material to real catalog records.
 *
 * Seeds the canonical `electronic_materials` table — the legacy uuid-keyed
 * `app.digital_materials` is not written to any more, since reader traffic is
 * resolved against bibliographic record ids.
 *
 * Each seeded material covers a different access level so the reading room can
 * be checked end to end: what a guest sees, what a signed-in reader sees, and
 * what stays closed to both.
 */
class DigitalMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $records = BibliographicRecord::query()
            ->orderBy('id')
            ->limit(3)
            ->get(['id', 'title']);

        if ($records->isEmpty()) {
            $this->command?->warn('No bibliographic records found — skipping digital material seeding.');

            return;
        }

        $disk = Storage::disk('local');
        $builder = new DemoPdfBuilder;

        // access_level, allow_download, page count
        $variants = [
            ['public', false, 12],
            ['authenticated', true, 18],
            ['restricted', false, 8],
        ];

        foreach ($records as $index => $record) {
            [$accessLevel, $allowDownload, $pageCount] = $variants[$index % count($variants)];

            $title = trim((string) $record->title) ?: 'Электронная версия';
            $path = "electronic-materials/demo-record-{$record->id}.pdf";

            // Rewrite the file even when the row exists: a checkout may have the
            // row from an earlier seed but not the file, which is exactly the
            // state that makes the reader look broken.
            $disk->put($path, $builder->build(
                $title,
                DemoPdfBuilder::samplePages($title, $pageCount),
            ));

            $existing = ElectronicMaterial::query()
                ->where('bibliographic_record_id', $record->id)
                ->where('file_path', $path)
                ->first();

            $attributes = [
                'bibliographic_record_id' => $record->id,
                'title' => mb_substr('Электронная версия: '.$title, 0, 500),
                'file_path' => $path,
                'external_url' => null,
                'file_type' => 'pdf',
                'file_size' => $disk->size($path),
                'access_level' => $accessLevel,
                'license_terms' => $allowDownload
                    ? 'CC BY-NC 4.0 — разрешено скачивание для учебных целей'
                    : 'Только просмотр в читальном зале библиотеки',
                'allow_download' => $allowDownload,
                'is_active' => true,
            ];

            if ($existing !== null) {
                $existing->update($attributes);
                $this->command?->info("Refreshed demo material for record {$record->id} (access: {$accessLevel}).");

                continue;
            }

            ElectronicMaterial::query()->create($attributes);

            $this->command?->info(
                "Seeded {$pageCount}-page demo material for record {$record->id} ".
                "(access: {$accessLevel}, download: ".($allowDownload ? 'yes' : 'no').').'
            );
        }
    }
}
