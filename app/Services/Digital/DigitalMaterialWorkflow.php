<?php

namespace App\Services\Digital;

use App\Models\Catalog\ElectronicMaterial;
use App\Models\Catalog\ElectronicMaterialVersion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\UploadedFileSecurity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DigitalMaterialWorkflow
{
    public const TRANSITIONS = [
        'uploaded' => ['quarantined'],
        'quarantined' => ['metadata_review', 'rejected'],
        'metadata_review' => ['rights_review', 'rejected'],
        'rights_review' => ['processing', 'restricted', 'rejected'],
        'processing' => ['ready_for_review', 'processing_failed'],
        'processing_failed' => ['processing', 'rejected'],
        'ready_for_review' => ['approved', 'rejected'],
        'approved' => ['published', 'restricted'],
        'published' => ['restricted', 'withdrawn', 'archived'],
        'restricted' => ['rights_review', 'withdrawn', 'archived'],
        'rejected' => ['metadata_review', 'archived'],
        'withdrawn' => ['archived'],
        'archived' => [],
    ];

    public const MIME_BY_TYPE = [
        'book_pdf' => ['application/pdf'],
        'image_collection' => ['image/jpeg', 'image/png', 'image/webp', 'image/tiff'],
        'presentation' => ['application/pdf', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/vnd.oasis.opendocument.presentation'],
        'scientific_work' => ['application/pdf'],
        'methodological_material' => [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.oasis.opendocument.text', 'application/rtf', 'text/plain',
        ],
        'supplementary_file' => ['application/pdf', 'text/plain', 'text/csv', 'application/zip'],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function attachFile(ElectronicMaterial $material, UploadedFile $file, User $actor, string $reason): ElectronicMaterial
    {
        UploadedFileSecurity::assertSafe($file);
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::MIME_BY_TYPE[$material->material_type] ?? [], true)) {
            throw ValidationException::withMessages(['file' => __('validation.mimetypes', ['attribute' => 'file', 'values' => implode(', ', self::MIME_BY_TYPE[$material->material_type] ?? [])])]);
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        if (ElectronicMaterial::query()->where('checksum_sha256', $checksum)->whereKeyNot($material->getKey())->exists()) {
            throw ValidationException::withMessages(['file' => __('digital.validation.duplicate_checksum')]);
        }

        $version = max(1, (int) $material->version_number + ($material->file_path ? 1 : 0));
        $safeName = Str::uuid().'.'.mb_strtolower($file->guessExtension() ?: 'bin');
        $path = $file->storeAs("digital-materials/{$material->public_id}/v{$version}", $safeName, 'local');
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['file' => __('digital.validation.storage_failed')]);
        }

        try {
            DB::transaction(function () use ($material, $file, $actor, $reason, $mime, $checksum, $safeName, $path, $version): void {
                ElectronicMaterial::query()->whereKey($material)->lockForUpdate()->firstOrFail();
                ElectronicMaterialVersion::query()->where('electronic_material_id', $material->getKey())->update(['is_active' => false]);
                ElectronicMaterialVersion::create([
                    'electronic_material_id' => $material->getKey(), 'version_number' => $version,
                    'storage_disk' => 'local', 'storage_path' => $path,
                    'original_filename' => $file->getClientOriginalName(), 'safe_filename' => $safeName,
                    'mime_type' => $mime, 'file_size' => $file->getSize(), 'checksum_sha256' => $checksum,
                    'change_reason' => $reason, 'created_by' => $actor->getKey(), 'is_active' => true,
                ]);
                $material->update([
                    'file_path' => $path, 'original_filename' => $file->getClientOriginalName(),
                    'safe_filename' => $safeName, 'storage_disk' => 'local', 'mime_type' => $mime,
                    'file_size' => $file->getSize(), 'checksum_sha256' => $checksum,
                    'version_number' => $version, 'workflow_status' => 'quarantined',
                ]);
                $this->audit->logRequired('digital.file_version_created', 'electronic_material', $material->getKey(), newValues: ['version' => $version, 'checksum' => $checksum], reason: $reason, scope: 'library');
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return $material->refresh();
    }

    public function transition(ElectronicMaterial $material, string $to, User $actor, ?string $reason = null): ElectronicMaterial
    {
        if (! in_array($to, self::TRANSITIONS[$material->workflow_status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('digital.validation.invalid_transition')]);
        }
        if ($to === 'published' && ! $material->rightsPermitPublication()) {
            throw ValidationException::withMessages(['copyright_status' => __('digital.validation.rights_required')]);
        }
        if ($to === 'withdrawn' && blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('repository.validation.reason_required')]);
        }

        DB::transaction(function () use ($material, $to, $actor, $reason): void {
            $locked = ElectronicMaterial::query()->whereKey($material)->lockForUpdate()->firstOrFail();
            $from = $locked->workflow_status;
            if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => __('digital.validation.invalid_transition')]);
            }
            $values = ['workflow_status' => $to];
            if ($to === 'approved') {
                $values += ['approved_by' => $actor->getKey()];
            }
            if ($to === 'published') {
                $values += ['published_at' => now(), 'is_active' => true];
            }
            if ($to === 'withdrawn') {
                $values += ['withdrawn_at' => now(), 'withdrawal_reason' => $reason, 'is_active' => false];
            }
            if ($to === 'archived') {
                $values += ['archived_at' => now(), 'is_active' => false];
            }
            $locked->update($values);
            $this->audit->logRequired("digital.{$to}", 'electronic_material', $locked->getKey(), oldValues: ['workflow_status' => $from], newValues: ['workflow_status' => $to], reason: $reason, scope: 'library');
        });

        return $material->refresh();
    }
}
