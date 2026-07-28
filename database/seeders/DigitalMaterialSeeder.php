<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DigitalMaterialSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure the table exists (migration may not have run in some environments).
        DB::statement('CREATE SCHEMA IF NOT EXISTS app');

        // Pick up to 3 real documents that have valid ISBNs.
        $docs = DB::table('app.documents')
            ->select('id', 'title_display', 'isbn_normalized')
            ->whereNotNull('isbn_normalized')
            ->where('isbn_normalized', '!=', '')
            ->limit(3)
            ->get();

        if ($docs->isEmpty()) {
            $this->command->warn('No documents found — skipping digital material seeding.');

            return;
        }

        // Create a minimal sample PDF in private storage.
        $samplePdfPath = 'digital-materials/sample-textbook.pdf';
        $disk = Storage::disk('local');

        if (! $disk->exists($samplePdfPath)) {
            $disk->makeDirectory('digital-materials');
            $disk->put($samplePdfPath, $this->minimalPdf());
            $this->command->info("Created sample PDF at {$samplePdfPath}");
        }

        $fileSize = $disk->size($samplePdfPath);

        foreach ($docs as $i => $doc) {
            $exists = DB::table('app.digital_materials')
                ->where('document_id', $doc->id)
                ->exists();

            if ($exists) {
                $this->command->info("Digital material already exists for document {$doc->id} — skipping.");

                continue;
            }

            $title = mb_substr($doc->title_display ?? 'Электронная версия', 0, 100);
            $accessLevel = match ($i) {
                0 => 'authenticated',
                1 => 'open',
                2 => 'campus',
                default => 'authenticated',
            };

            DB::table('app.digital_materials')->insert([
                'id' => Str::uuid()->toString(),
                'document_id' => $doc->id,
                'title' => "Электронная версия: {$title}",
                'file_type' => 'pdf',
                'storage_disk' => 'local',
                'storage_path' => $samplePdfPath,
                'original_filename' => 'textbook.pdf',
                'file_size_bytes' => $fileSize,
                'access_level' => $accessLevel,
                'allow_download' => false,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("Seeded digital material for doc {$doc->id} (ISBN: {$doc->isbn_normalized}, access: {$accessLevel})");
        }
    }

    /**
     * Generate a minimal valid PDF for demo purposes.
     */
    private function minimalPdf(): string
    {
        // Minimal valid PDF 1.4 with one page containing text.
        return "%PDF-1.4\n" .
            "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n" .
            "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n" .
            "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n" .
            "4 0 obj<</Length 83>>\nstream\n" .
            "BT\n/F1 24 Tf\n100 700 Td\n(Digital Library) Tj\n0 -40 Td\n/F1 14 Tf\n(Sample digital material) Tj\nET\n" .
            "endstream\nendobj\n" .
            "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n" .
            "xref\n0 6\n" .
            "0000000000 65535 f \n" .
            "0000000009 00000 n \n" .
            "0000000058 00000 n \n" .
            "0000000115 00000 n \n" .
            "0000000266 00000 n \n" .
            "0000000399 00000 n \n" .
            "trailer<</Size 6/Root 1 0 R>>\n" .
            "startxref\n466\n%%EOF";
    }
}
