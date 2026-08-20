<?php

namespace App\Services\Library;

/**
 * One digital material, flattened out of whichever table it came from.
 *
 * Materials live in two places — the canonical `electronic_materials` keyed by
 * bibliographic record id, and the legacy uuid-keyed `app.digital_materials`.
 * The two use different column names and different access-level vocabularies.
 * Everything downstream of DigitalAccessService works on this shape instead, so
 * the viewer, the stream route and the download route each have one code path.
 */
final readonly class ResolvedDigitalMaterial
{
    /**
     * @param  string  $ref  Stable cross-table key: "em:<id>" or "dm:<uuid>".
     * @param  string  $accessLevel  Normalised: public|authenticated|campus|restricted.
     */
    public function __construct(
        public string $ref,
        public string $id,
        public string $title,
        public string $fileType,
        public int $fileSizeBytes,
        public string $accessLevel,
        public string $licenseTerms,
        public bool $allowDownload,
        public bool $isActive,
        public ?string $externalUrl,
        public ?string $storageDisk,
        public ?string $storagePath,
        public string $originalFilename,
        public array $restrictedRoles = [],
        public bool $campusOnly = false,
        public ?\DateTimeInterface $embargoUntil = null,
        public string $workflowStatus = 'published',
        public string $downloadPolicy = 'disabled',
        public string $printPolicy = 'disabled',
        public string $copyPolicy = 'disabled',
    ) {}

    /**
     * True when the bytes are held by the library rather than by a third party.
     * Externally hosted material can be linked but never streamed or paginated.
     */
    public function hasLocalFile(): bool
    {
        return $this->storagePath !== null && $this->storagePath !== '';
    }

    /**
     * Whether the in-house reader can paginate this material. Only PDF is
     * rendered page by page; other formats fall back to a download or a link.
     */
    public function isReadableInViewer(): bool
    {
        return $this->hasLocalFile() && $this->fileType === 'pdf';
    }

    public function humanFileSize(): string
    {
        if ($this->fileSizeBytes >= 1048576) {
            return round($this->fileSizeBytes / 1048576, 1).' МБ';
        }

        if ($this->fileSizeBytes >= 1024) {
            return round($this->fileSizeBytes / 1024).' КБ';
        }

        return $this->fileSizeBytes.' Б';
    }
}
