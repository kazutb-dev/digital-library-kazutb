<?php

namespace App\Services\DataQuality;

use App\Models\Catalog\BibliographicRecord;
use App\Models\DataImportBatch;
use App\Models\DataImportMappingProfile;
use App\Models\DataImportStagingRow;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Library\IsbnService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportStagingService
{
    public const SUPPORTED_FORMATS = ['csv', 'json_mapping_fixture'];

    public function __construct(
        private readonly IsbnService $isbn,
        private readonly DuplicateDetectionService $duplicates,
        private readonly AuditLogger $audit,
        private readonly DataQualityScanner $scanner,
        private readonly DataQualityNotificationService $notifications,
    ) {}

    public function upload(UploadedFile $file, string $format, ?DataImportMappingProfile $profile, User $actor, ?string $encoding = null): DataImportBatch
    {
        if (! in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new RuntimeException('This source format has an adapter contract but no verified parser.');
        }
        $bytes = $file->get();
        $checksum = hash('sha256', $bytes);
        if (DataImportBatch::query()->where('checksum', $checksum)->where('source_format', $format)->exists()) {
            throw new RuntimeException('This exact source file has already been uploaded.');
        }
        $detection = $this->detectEncoding($bytes);
        $selectedEncoding = $encoding ?: $detection['encoding'];
        if (! in_array($selectedEncoding, $this->allowedEncodings(), true)) {
            throw new RuntimeException('The selected source encoding is not allowed.');
        }
        $utf8 = $selectedEncoding === 'UTF-8' ? $bytes : mb_convert_encoding($bytes, 'UTF-8', $selectedEncoding);

        return DB::transaction(function () use ($file, $format, $profile, $actor, $checksum, $detection, $selectedEncoding, $utf8): DataImportBatch {
            $batch = DataImportBatch::query()->create([
                'batch_number' => 'IMP-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'source_format' => $format,
                'source_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                'checksum' => $checksum,
                'status' => 'parsing',
                'detected_encoding' => $detection['encoding'],
                'encoding_confidence' => $detection['confidence'],
                'selected_encoding' => $selectedEncoding,
                'mapping_profile_id' => $profile?->getKey(),
                'mapping_version' => $profile?->version,
                'uploaded_by' => $actor->getKey(),
            ]);
            $this->audit->logRequired('data_quality.import_uploaded', 'data_import_batch', $batch->getKey(), newValues: $batch->toArray(), scope: 'operational', actor: $actor);

            $rows = $this->parse($utf8, $format);
            $stats = ['valid' => 0, 'error' => 0];
            foreach ($rows as $index => $raw) {
                $mapped = $this->map($raw, $profile?->mapping ?? []);
                $errors = $this->validate($mapped);
                $duplicateCandidates = $errors === []
                    ? $this->duplicates->candidates($mapped)->take(5)->map(fn (array $match): array => [
                        'id' => $match['record']->getKey(),
                        'score' => $match['score'],
                        'level' => $match['level'],
                        'details' => $match['details'],
                    ])->all()
                    : [];
                $status = $errors !== [] ? 'validation_failed' : ($duplicateCandidates !== [] ? 'review_required' : 'ready');
                $stats[$errors === [] ? 'valid' : 'error']++;
                DataImportStagingRow::query()->create([
                    'batch_id' => $batch->getKey(),
                    'source_row_id' => (string) ($raw['source_id'] ?? $index + 1),
                    'raw_payload' => $raw,
                    'normalized_payload' => $this->normalizePayload($mapped),
                    'mapping_result' => $mapped,
                    'validation_errors' => $errors ?: null,
                    'duplicate_candidates' => $duplicateCandidates ?: null,
                    'proposed_action' => $duplicateCandidates !== [] ? 'possible_duplicate' : ($errors === [] ? 'create' : 'review'),
                    'status' => $status,
                ]);
            }
            $batch->update([
                'status' => $stats['error'] > 0 || $batch->rows()->where('status', 'review_required')->exists() ? 'review_required' : 'staged',
                'rows_total' => count($rows),
                'rows_valid' => $stats['valid'],
                'rows_error' => $stats['error'],
            ]);
            $this->audit->logRequired('data_quality.import_staged', 'data_import_batch', $batch->getKey(), newValues: $batch->fresh()->toArray(), scope: 'operational', actor: $actor);
            if ($batch->status === 'review_required') {
                $this->notifications->approvalDigest(
                    'data_quality.approve_import',
                    'data_quality_import_review_required',
                    'data_quality.notifications.import_review_title',
                    'data_quality.notifications.import_review_body',
                    ['number' => $batch->batch_number],
                    ['import_batch_id' => $batch->getKey()],
                );
            }

            return $batch->fresh('rows');
        });
    }

    public function approve(DataImportBatch $batch, User $actor): DataImportBatch
    {
        return DB::transaction(function () use ($batch, $actor): DataImportBatch {
            $batch = DataImportBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            if (! in_array($batch->status, ['staged', 'review_required', 'validation_failed'], true)) {
                throw new RuntimeException('This import cannot be approved from its current state.');
            }
            if ((int) $batch->uploaded_by === (int) $actor->getKey()) {
                throw new RuntimeException('The importer cannot perform the independent approval.');
            }
            if ($batch->rows()->where('status', 'review_required')->where('proposed_action', 'possible_duplicate')->exists()) {
                throw new RuntimeException('Every possible duplicate needs an explicit create, update or skip decision.');
            }
            $batch->update(['status' => 'ready', 'approved_by' => $actor->getKey(), 'approved_at' => now()]);
            $this->audit->logRequired('data_quality.import_approved', 'data_import_batch', $batch->getKey(), newValues: $batch->toArray(), scope: 'operational', actor: $actor);

            return $batch;
        });
    }

    public function decideRow(DataImportStagingRow $row, string $action): DataImportStagingRow
    {
        if (! in_array($action, ['create', 'update', 'skip', 'review'], true)) {
            throw new RuntimeException('Unsupported import row action.');
        }
        if ($action === 'update' && empty($row->duplicate_candidates)) {
            throw new RuntimeException('An update requires a selected existing duplicate.');
        }
        $row->update(['proposed_action' => $action, 'status' => $action === 'skip' ? 'skipped' : ($action === 'review' ? 'review_required' : 'ready')]);

        return $row;
    }

    public function import(DataImportBatch $batch, User $actor): DataImportBatch
    {
        $batch = DB::transaction(function () use ($batch): DataImportBatch {
            $batch = DataImportBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            if ($batch->status !== 'ready') {
                throw new RuntimeException('Only an approved ready batch can enter production tables.');
            }
            $batch->update(['status' => 'importing', 'started_at' => now()]);

            return $batch;
        });

        $created = $updated = $skipped = $failed = 0;
        foreach ($batch->rows()->orderBy('id')->cursor() as $row) {
            if ($row->proposed_action === 'skip' || $row->status === 'validation_failed') {
                $skipped++;

                continue;
            }
            try {
                DB::transaction(function () use ($row, &$created, &$updated): void {
                    $payload = $row->mapping_result;
                    if ($row->proposed_action === 'update') {
                        $targetId = data_get($row->duplicate_candidates, '0.id');
                        $record = BibliographicRecord::query()->lockForUpdate()->findOrFail($targetId);
                        $record->fill($payload)->save();
                        $updated++;
                    } else {
                        $record = BibliographicRecord::query()->create($payload + [
                            'is_draft' => $this->isDraft($payload),
                            'needs_manual_review' => false,
                        ]);
                        $created++;
                    }
                    $row->update(['status' => 'imported', 'final_entity_id' => $record->getKey()]);
                    $this->scanner->scanModel($record->fresh(), 'bibliographic_record');
                    $this->duplicates->detectAndStore($record->fresh());
                });
            } catch (Throwable $exception) {
                $row->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage(), 0, 65000)]);
                $failed++;
            }
        }
        $reconciliation = [
            'source_rows' => $batch->rows_total,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'accounted_rows' => $created + $updated + $skipped + $failed,
            'difference' => $batch->rows_total - ($created + $updated + $skipped + $failed),
        ];
        $batch->update([
            'status' => $failed === 0 ? 'imported' : (($created + $updated) > 0 ? 'partially_imported' : 'failed'),
            'rows_imported' => $created + $updated,
            'rows_skipped' => $skipped,
            'finished_at' => now(),
            'reconciliation' => $reconciliation,
        ]);
        $this->audit->logRequired('data_quality.import_completed', 'data_import_batch', $batch->getKey(), newValues: $batch->fresh()->toArray(), scope: 'operational', actor: $actor);
        if ($failed > 0) {
            $this->notifications->approvalDigest(
                'data_quality.approve_import',
                'data_quality_import_completed_with_errors',
                'data_quality.notifications.import_error_title',
                'data_quality.notifications.import_error_body',
                ['number' => $batch->batch_number, 'failed' => $failed],
                ['import_batch_id' => $batch->getKey()],
            );
        }

        return $batch->fresh('rows');
    }

    /** @return array{encoding:string,confidence:float,preview:string,problem_offsets:list<int>} */
    public function detectEncoding(string $bytes): array
    {
        $validUtf8 = mb_check_encoding($bytes, 'UTF-8');
        $encoding = $validUtf8 ? 'UTF-8' : (mb_detect_encoding($bytes, ['Windows-1251', 'ISO-8859-5'], true) ?: 'Windows-1251');
        $converted = $encoding === 'UTF-8' ? $bytes : mb_convert_encoding($bytes, 'UTF-8', $encoding);
        $problemOffsets = [];
        foreach (str_split($converted) as $offset => $byte) {
            if ($byte === "\0") {
                $problemOffsets[] = $offset;
            }
        }

        return [
            'encoding' => $encoding,
            'confidence' => $validUtf8 ? 100.0 : 70.0,
            'preview' => mb_substr($converted, 0, 1000),
            'problem_offsets' => $problemOffsets,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function parse(string $content, string $format): array
    {
        if ($format === 'json_mapping_fixture') {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return array_is_list($decoded) ? $decoded : [$decoded];
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $content);
        rewind($stream);
        $header = fgetcsv($stream);
        if (! is_array($header) || $header === []) {
            throw new RuntimeException('The CSV header is missing.');
        }
        $header = array_map(fn ($value) => Str::snake(trim((string) $value)), $header);
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            if (count($values) !== count($header)) {
                $rows[] = ['_parse_error' => 'Column count differs from the header'];

                continue;
            }
            $rows[] = array_combine($header, $values);
        }
        fclose($stream);

        return $rows;
    }

    /** @param array<string,mixed> $mapping */
    private function map(array $raw, array $mapping): array
    {
        if (isset($raw['_parse_error'])) {
            return $raw;
        }
        if ($mapping === []) {
            return array_intersect_key($raw, array_flip((new BibliographicRecord)->getFillable()));
        }
        $result = [];
        foreach ($mapping as $source => $definition) {
            $target = is_array($definition) ? ($definition['target'] ?? null) : $definition;
            if (! is_string($target) || $target === '') {
                continue;
            }
            $value = $raw[$source] ?? (is_array($definition) ? ($definition['fallback'] ?? null) : null);
            $result[$target] = $this->transform($value, is_array($definition) ? ($definition['transform'] ?? null) : null);
        }

        return $result;
    }

    private function transform(mixed $value, ?string $transformation): mixed
    {
        return match ($transformation) {
            'trim' => trim((string) $value),
            'isbn' => $this->isbn->normalize((string) $value),
            'integer' => is_numeric($value) ? (int) $value : $value,
            'lowercase' => mb_strtolower(trim((string) $value)),
            default => $value,
        };
    }

    /** @return list<string> */
    private function validate(array $payload): array
    {
        $errors = [];
        if (isset($payload['_parse_error'])) {
            return [$payload['_parse_error']];
        }
        if (trim((string) ($payload['title'] ?? '')) === '') {
            $errors[] = 'title.required';
        }
        if (isset($payload['publication_year']) && (! is_numeric($payload['publication_year']) || (int) $payload['publication_year'] > (int) now()->format('Y') + 1)) {
            $errors[] = 'publication_year.invalid';
        }
        if (! empty($payload['isbn']) && ! $this->isbn->validate((string) $payload['isbn'])['valid']) {
            $errors[] = 'isbn.invalid';
        }

        return $errors;
    }

    private function normalizePayload(array $payload): array
    {
        return collect($payload)->map(fn ($value) => is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value) ?? $value) : $value)->all();
    }

    private function isDraft(array $payload): bool
    {
        return collect(BibliographicRecord::REQUIRED_FOR_COMPLETE)->contains(fn (string $field) => trim((string) ($payload[$field] ?? '')) === '');
    }

    /** @return list<string> */
    private function allowedEncodings(): array
    {
        return (array) Setting::valueFor('data_quality_import_encodings', ['UTF-8', 'Windows-1251']);
    }
}
