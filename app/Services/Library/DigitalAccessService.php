<?php

namespace App\Services\Library;

use App\Models\Catalog\ElectronicMaterial;
use App\Models\Library\DigitalMaterial;
use App\Models\Library\DigitalReadingProgress;
use App\Support\DatabaseSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The single gate in front of digital materials: resolves a material from
 * whichever table holds it, decides whether the requester may read it, and
 * remembers where each reader left off.
 *
 * Every reader-facing entry point (viewer page, stream, download, progress) goes
 * through here, so an access rule can only be written once. Prior to this the
 * canonical and legacy tables each carried their own copy of the rules and had
 * already drifted apart.
 */
class DigitalAccessService
{
    /** Permissions that let staff reach `restricted` material. */
    private const STAFF_PERMISSIONS = ['digital.upload', 'digital.set_access_flags'];

    /**
     * Active materials attached to a document, in display order.
     *
     * @return list<ResolvedDigitalMaterial>
     */
    public function forDocument(string $documentId): array
    {
        if (ctype_digit($documentId) && DatabaseSchema::hasTable('electronic_materials')) {
            return ElectronicMaterial::query()
                ->where('bibliographic_record_id', (int) $documentId)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->get()
                ->map(fn (ElectronicMaterial $material) => $this->fromCanonical($material))
                ->all();
        }

        if (! DatabaseSchema::hasTable('app.digital_materials', 'pgsql')) {
            return [];
        }

        return DigitalMaterial::where('document_id', $documentId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn (DigitalMaterial $material) => $this->fromLegacy($material))
            ->all();
    }

    public function hasDigitalMaterial(string $documentId): bool
    {
        return $this->forDocument($documentId) !== [];
    }

    /**
     * Resolve a single active material by its route id. A numeric id always
     * means the canonical table; anything else is a legacy uuid.
     */
    public function resolve(string $id): ?ResolvedDigitalMaterial
    {
        if (ctype_digit($id)) {
            if (! DatabaseSchema::hasTable('electronic_materials')) {
                return null;
            }

            $material = ElectronicMaterial::query()
                ->whereKey((int) $id)
                ->where('is_active', true)
                ->first();

            return $material === null ? null : $this->fromCanonical($material);
        }

        if (! DatabaseSchema::hasTable('app.digital_materials', 'pgsql')) {
            return null;
        }

        $material = DigitalMaterial::where('id', $id)
            ->where('is_active', true)
            ->first();

        return $material === null ? null : $this->fromLegacy($material);
    }

    /**
     * Whether the requester may read the material at all.
     */
    public function canAccess(ResolvedDigitalMaterial $material, Request $request): bool
    {
        if (! $material->isActive) {
            return false;
        }

        return match ($material->accessLevel) {
            'public' => true,
            'authenticated' => $this->isIdentified($request),
            'campus' => $this->isIdentified($request) && $this->isOnCampus($request),
            'restricted' => $this->isStaff($request),
            default => false,
        };
    }

    /**
     * Downloading is a second, narrower decision: the reader must be allowed to
     * read the material *and* its licence must permit a copy to leave the
     * viewer. Librarians control the flag per material and it defaults to off.
     */
    public function canDownload(ResolvedDigitalMaterial $material, Request $request): bool
    {
        return $material->allowDownload
            && $material->hasLocalFile()
            && $this->canAccess($material, $request);
    }

    /**
     * Localised explanation for a denied read, safe to show to the requester.
     */
    public function accessDeniedReason(ResolvedDigitalMaterial $material, Request $request): string
    {
        if (! $material->isActive) {
            return (string) __('ui.digital.denied_inactive');
        }

        return match ($material->accessLevel) {
            'authenticated' => (string) __('ui.digital.denied_authenticated'),
            'campus' => $this->isIdentified($request)
                ? (string) __('ui.digital.denied_campus')
                : (string) __('ui.digital.denied_campus_guest'),
            'restricted' => (string) __('ui.digital.denied_restricted'),
            default => (string) __('ui.digital.denied_generic'),
        };
    }

    /**
     * The reader-facing payload for one material, including where the reader
     * should be sent to open it.
     *
     * Library-held material always opens in the controlled viewer rather than
     * linking straight at the stream: the viewer is what enforces paginated
     * reading and keeps the file out of the browser's own PDF UI, which offers
     * a save button regardless of the material's licence.
     *
     * @return array<string, mixed>
     */
    public function toReaderPayload(ResolvedDigitalMaterial $material, Request $request): array
    {
        $canAccess = $this->canAccess($material, $request);

        $viewerUrl = null;
        if ($canAccess) {
            $viewerUrl = $material->hasLocalFile()
                ? "/digital-viewer/{$material->id}"
                : $material->externalUrl;
        }

        return [
            'id' => $material->id,
            'title' => $material->title,
            'fileType' => $material->fileType,
            'fileSize' => $material->humanFileSize(),
            'fileSizeBytes' => $material->fileSizeBytes,
            'accessLevel' => $material->accessLevel,
            'licenseTerms' => $material->licenseTerms,
            'allowDownload' => $material->allowDownload,
            'canAccess' => $canAccess,
            'accessDeniedReason' => $canAccess ? null : $this->accessDeniedReason($material, $request),
            'viewerUrl' => $viewerUrl,
            'isExternal' => ! $material->hasLocalFile(),
            'downloadUrl' => $this->canDownload($material, $request)
                ? "/api/v1/digital-materials/{$material->id}/download"
                : null,
        ];
    }

    // ── Reading position ──────────────────────────────────────────

    /**
     * Stable identity for progress rows. Mirrors ShortlistStorageService so a
     * reader's position and their shortlist are keyed the same way.
     */
    public function readerId(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $user = $request->session()->get('library.user');

        if (is_array($user) && isset($user['id']) && (string) $user['id'] !== '') {
            return (string) $user['id'];
        }

        return null;
    }

    /**
     * The reader's saved position, or null when nothing is stored.
     *
     * @return array{page: int, totalPages: ?int, zoom: ?string}|null
     */
    public function readingProgress(ResolvedDigitalMaterial $material, Request $request): ?array
    {
        $readerId = $this->readerId($request);

        if ($readerId === null || ! config('digital_access.track_reading_progress')) {
            return null;
        }

        $row = DigitalReadingProgress::query()
            ->where('user_id', $readerId)
            ->where('material_ref', $material->ref)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'page' => max(1, (int) $row->page),
            'totalPages' => $row->total_pages,
            'zoom' => $row->zoom,
        ];
    }

    /**
     * Store the reader's position. Returns false when there is nobody to store
     * it against, so the caller can report that rather than silently succeed.
     */
    public function saveReadingProgress(
        ResolvedDigitalMaterial $material,
        Request $request,
        int $page,
        ?int $totalPages = null,
        ?string $zoom = null,
    ): bool {
        $readerId = $this->readerId($request);

        if ($readerId === null || ! config('digital_access.track_reading_progress')) {
            return false;
        }

        DigitalReadingProgress::updateOrCreate(
            ['user_id' => $readerId, 'material_ref' => $material->ref],
            [
                'page' => max(1, $page),
                'total_pages' => $totalPages,
                'zoom' => $zoom,
                'last_read_at' => now(),
            ],
        );

        return true;
    }

    // ── File access ───────────────────────────────────────────────

    /**
     * Absolute filesystem path for a locally held material, or null when the
     * bytes are missing or the disk is not local.
     *
     * A real path lets the response go out as a BinaryFileResponse, which
     * answers HTTP Range requests. The reader depends on that: pdf.js fetches a
     * large PDF in chunks, and without ranges every page turn in a 50 MB scan
     * would re-download the whole file.
     */
    public function localFilePath(ResolvedDigitalMaterial $material): ?string
    {
        if (! $material->hasLocalFile()) {
            return null;
        }

        $diskName = $material->storageDisk ?? 'local';

        if (config("filesystems.disks.{$diskName}.driver") !== 'local') {
            return null;
        }

        $disk = Storage::disk($diskName);

        if (! $disk->exists($material->storagePath)) {
            return null;
        }

        return $disk->path($material->storagePath);
    }

    public function fileExists(ResolvedDigitalMaterial $material): bool
    {
        if (! $material->hasLocalFile()) {
            return false;
        }

        return Storage::disk($material->storageDisk ?? 'local')->exists($material->storagePath);
    }

    public function mimeTypeFor(ResolvedDigitalMaterial $material): string
    {
        return match ($material->fileType) {
            'pdf' => 'application/pdf',
            'epub' => 'application/epub+zip',
            'djvu' => 'image/vnd.djvu',
            'image' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }

    // ── Requester identity ────────────────────────────────────────

    private function isIdentified(Request $request): bool
    {
        if ($request->user() !== null) {
            return true;
        }

        return $request->hasSession() && is_array($request->session()->get('library.user'));
    }

    private function isStaff(Request $request): bool
    {
        return $request->user()?->canAny(self::STAFF_PERMISSIONS) ?? false;
    }

    /**
     * With no configured ranges nothing is on-campus — see config/digital_access.php.
     */
    private function isOnCampus(Request $request): bool
    {
        $ranges = (array) config('digital_access.campus_ranges', []);

        if ($ranges === []) {
            return false;
        }

        $ip = $request->ip();

        return $ip !== null && IpUtils::checkIp($ip, $ranges);
    }

    // ── Table adapters ────────────────────────────────────────────

    private function fromCanonical(ElectronicMaterial $material): ResolvedDigitalMaterial
    {
        $path = (string) ($material->file_path ?? '');

        return new ResolvedDigitalMaterial(
            ref: 'em:'.$material->getKey(),
            id: (string) $material->getKey(),
            title: (string) $material->title,
            fileType: (string) $material->file_type,
            fileSizeBytes: (int) ($material->file_size ?? 0),
            accessLevel: $this->normaliseAccessLevel((string) $material->access_level),
            licenseTerms: (string) ($material->license_terms ?? ''),
            allowDownload: (bool) $material->allow_download,
            isActive: (bool) $material->is_active,
            externalUrl: $material->external_url,
            storageDisk: $path === '' ? null : 'local',
            storagePath: $path === '' ? null : $path,
            originalFilename: $path === '' ? (string) $material->title : basename($path),
        );
    }

    private function fromLegacy(DigitalMaterial $material): ResolvedDigitalMaterial
    {
        return new ResolvedDigitalMaterial(
            ref: 'dm:'.$material->id,
            id: (string) $material->id,
            title: (string) $material->title,
            fileType: (string) $material->file_type,
            fileSizeBytes: (int) $material->file_size_bytes,
            accessLevel: $this->normaliseAccessLevel((string) $material->access_level),
            licenseTerms: '',
            allowDownload: (bool) $material->allow_download,
            isActive: (bool) $material->is_active,
            externalUrl: null,
            storageDisk: (string) ($material->storage_disk ?: 'local'),
            storagePath: (string) $material->storage_path,
            originalFilename: (string) $material->original_filename,
        );
    }

    /**
     * The two tables spell the "anyone may read this" level differently —
     * canonical says `public`, legacy says `open`. Normalise to one vocabulary
     * so the access rules above never branch on which table a row came from.
     */
    private function normaliseAccessLevel(string $level): string
    {
        return match (mb_strtolower(trim($level))) {
            'open', 'public' => 'public',
            'authenticated' => 'authenticated',
            'campus' => 'campus',
            'restricted' => 'restricted',
            default => 'restricted',
        };
    }
}
