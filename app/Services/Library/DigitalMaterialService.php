<?php

namespace App\Services\Library;

use App\Models\Library\DigitalMaterial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DigitalMaterialService
{
    /**
     * Get active digital materials for a document.
     *
     * @return Collection<int, DigitalMaterial>
     */
    public function forDocument(string $documentId): Collection
    {
        return DigitalMaterial::where('document_id', $documentId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Check if a document has any active digital materials.
     */
    public function hasDigitalMaterial(string $documentId): bool
    {
        return DigitalMaterial::where('document_id', $documentId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Determine if a user can access a given material.
     *
     * @param  array<string, mixed>|null  $user  Session user data
     */
    public function canAccess(DigitalMaterial $material, ?array $user): bool
    {
        if (! $material->is_active) {
            return false;
        }

        return match ($material->access_level) {
            'open' => true,
            'authenticated' => $user !== null,
            'campus' => $user !== null, // future: add IP range check
            default => false,
        };
    }

    /**
     * Get the reason a user cannot access a material.
     */
    public function accessDeniedReason(DigitalMaterial $material, ?array $user): string
    {
        if (! $material->is_active) {
            return 'Материал временно недоступен.';
        }

        if ($material->access_level === 'authenticated' && $user === null) {
            return 'Для просмотра электронного материала необходимо войти в систему.';
        }

        if ($material->access_level === 'campus' && $user === null) {
            return 'Для просмотра этого материала необходимо войти в систему и подключиться из сети университета.';
        }

        return 'Доступ к материалу ограничен.';
    }

    /**
     * Resolve the material or return null if not found/inactive.
     */
    public function findActive(string $materialId): ?DigitalMaterial
    {
        return DigitalMaterial::where('id', $materialId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Stream the file for in-browser viewing (PDF inline).
     */
    public function streamForViewing(DigitalMaterial $material): StreamedResponse
    {
        $disk = Storage::disk($material->storage_disk);
        $path = $material->storage_path;

        if (! $disk->exists($path)) {
            abort(404, 'Файл не найден.');
        }

        $mimeTypes = [
            'pdf' => 'application/pdf',
            'epub' => 'application/epub+zip',
            'djvu' => 'image/vnd.djvu',
        ];

        $mime = $mimeTypes[$material->file_type] ?? 'application/octet-stream';

        return response()->stream(
            function () use ($disk, $path): void {
                $stream = $disk->readStream($path);
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . $material->original_filename . '"',
                'Content-Length' => $disk->size($path),
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
