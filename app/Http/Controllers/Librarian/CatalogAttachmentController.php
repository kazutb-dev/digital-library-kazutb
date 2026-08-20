<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\ElectronicMaterial;
use App\Services\AuditLogger;
use App\Services\Digital\DigitalMaterialWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Attachments of a bibliographic record that the cataloguing form edits
 * alongside the metadata: electronic materials (Master.md 18) and links to
 * other records (10.4). Kept out of CatalogController because each of these
 * is its own resource with its own audit trail — the metadata form itself
 * stays a single PATCH.
 */
class CatalogAttachmentController extends Controller
{
    /**
     * Acceptable MIME types per declared file_type. Deliberately permissive
     * within each type — legacy scans arrive as any of several image encodings.
     *
     * @var array<string, list<string>>
     */
    private const MIME_BY_FILE_TYPE = [
        'pdf' => ['application/pdf'],
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/tiff', 'image/gif'],
        'presentation' => [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.presentation',
            'application/pdf',
        ],
        'document' => [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.oasis.opendocument.text',
            'application/rtf',
            'text/plain',
            'application/pdf',
        ],
    ];

    /**
     * Catalog autocomplete for the "related materials" picker. Mirrors the
     * shape of CatalogController::udcSearch so the form can reuse one fetch
     * helper for both pickers.
     */
    public function recordSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $exclude = (int) $request->query('exclude', 0);

        if ($term === '') {
            return response()->json(['data' => []]);
        }

        $records = BibliographicRecord::query()
            ->search($term)
            ->when($exclude > 0, function (Builder $builder) use ($exclude): void {
                // Exclude the record being edited and everything already
                // linked to it, so the picker never offers a no-op.
                $builder->whereKeyNot($exclude)->whereNotIn(
                    'id',
                    DB::table('bibliographic_record_relations')
                        ->where('record_id', $exclude)
                        ->pluck('related_record_id'),
                );
            })
            ->orderBy('title')
            ->limit(15)
            ->get(['id', 'title', 'primary_author', 'publication_year', 'isbn'])
            ->map(fn (BibliographicRecord $record): array => [
                'id' => $record->getKey(),
                'title' => (string) $record->title,
                'author' => (string) ($record->primary_author ?? ''),
                'year' => $record->publication_year,
                'isbn' => (string) ($record->isbn ?? ''),
            ]);

        return response()->json(['data' => $records]);
    }

    public function storeMaterial(
        Request $request,
        BibliographicRecord $record,
        AuditLogger $audit,
        DigitalMaterialWorkflow $workflow,
    ): RedirectResponse {
        $validated = $this->validatedMaterial($request, required: true);

        DB::transaction(function () use ($request, $record, $validated, $audit, $workflow): void {
            $material = new ElectronicMaterial($validated);
            $material->bibliographic_record_id = $record->getKey();
            $material->uploaded_by = $request->user()->getKey();
            $this->prepareWorkflowDefaults($material);
            $material->save();

            $audit->logRequired(
                actionType: 'digital.upload',
                entityType: 'electronic_material',
                entityId: $material->getKey(),
                newValues: $this->materialSnapshot($material),
                scope: 'library',
            );

            if ($request->hasFile('file')) {
                $workflow->attachFile(
                    $material,
                    $request->file('file'),
                    $request->user(),
                    (string) $request->input('version_reason', __('digital.version.initial_upload')),
                );
            }
        });

        return redirect()
            ->route('librarian.catalog.edit', $record)
            ->with('success', __('librarian.catalog.materials.created'));
    }

    public function updateMaterial(
        Request $request,
        BibliographicRecord $record,
        ElectronicMaterial $material,
        AuditLogger $audit,
        DigitalMaterialWorkflow $workflow,
    ): RedirectResponse {
        $this->assertBelongsTo($material, $record);

        // On update the source may stay untouched, so neither a file nor a URL
        // is required — only that the material still ends up with one.
        $validated = $this->validatedMaterial($request, required: false);

        DB::transaction(function () use ($request, $material, $validated, $audit, $workflow): void {
            $old = $this->materialSnapshot($material);
            $material->fill($validated);

            if (! $request->hasFile('file') && (string) $material->file_path === '' && (string) $material->external_url === '') {
                throw ValidationException::withMessages([
                    'external_url' => __('librarian.catalog.materials.source_required'),
                ]);
            }

            $material->save();

            $audit->logRequired(
                actionType: 'metadata.update',
                entityType: 'electronic_material',
                entityId: $material->getKey(),
                oldValues: $old,
                newValues: $this->materialSnapshot($material),
                scope: 'library',
            );

            if ($request->hasFile('file')) {
                $workflow->attachFile(
                    $material,
                    $request->file('file'),
                    $request->user(),
                    (string) $request->input('version_reason', __('digital.version.replacement')),
                );
            }
        });

        return redirect()
            ->route('librarian.catalog.edit', $record)
            ->with('success', __('librarian.catalog.materials.updated'));
    }

    public function destroyMaterial(
        BibliographicRecord $record,
        ElectronicMaterial $material,
        AuditLogger $audit,
    ): RedirectResponse {
        $this->assertBelongsTo($material, $record);

        DB::transaction(function () use ($material, $audit): void {
            $old = $this->materialSnapshot($material);
            $id = $material->getKey();
            $material->delete();

            $audit->logRequired(
                actionType: 'delete',
                entityType: 'electronic_material',
                entityId: $id,
                oldValues: $old,
                scope: 'library',
            );
        });

        return redirect()
            ->route('librarian.catalog.edit', $record)
            ->with('success', __('librarian.catalog.materials.deleted'));
    }

    public function storeRelation(Request $request, BibliographicRecord $record, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'related_record_id' => [
                'required',
                'integer',
                Rule::exists('bibliographic_records', 'id'),
                Rule::notIn([$record->getKey()]),
            ],
        ]);

        $relatedId = (int) $validated['related_record_id'];

        DB::transaction(function () use ($record, $relatedId, $audit): void {
            // Written both ways: readers reach the link from either card, and
            // BookDetailReadService only follows record_id -> related_record_id.
            $record->relatedRecords()->syncWithoutDetaching([$relatedId]);
            BibliographicRecord::query()
                ->findOrFail($relatedId)
                ->relatedRecords()
                ->syncWithoutDetaching([$record->getKey()]);

            $audit->logRequired(
                actionType: 'metadata.update',
                entityType: 'bibliographic_record',
                entityId: $record->getKey(),
                newValues: ['related_record_added' => $relatedId],
                scope: 'library',
            );
        });

        return redirect()
            ->route('librarian.catalog.edit', $record)
            ->with('success', __('librarian.catalog.relations.created'));
    }

    public function destroyRelation(
        BibliographicRecord $record,
        BibliographicRecord $related,
        AuditLogger $audit,
    ): RedirectResponse {
        DB::transaction(function () use ($record, $related, $audit): void {
            $record->relatedRecords()->detach($related->getKey());
            $related->relatedRecords()->detach($record->getKey());

            $audit->logRequired(
                actionType: 'metadata.update',
                entityType: 'bibliographic_record',
                entityId: $record->getKey(),
                oldValues: ['related_record_removed' => $related->getKey()],
                scope: 'library',
            );
        });

        return redirect()
            ->route('librarian.catalog.edit', $record)
            ->with('success', __('librarian.catalog.relations.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMaterial(Request $request, bool $required): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'file' => ['nullable', 'file', 'max:51200'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'file_type' => ['required', Rule::in(ElectronicMaterial::FILE_TYPES)],
            'access_level' => ['required', Rule::in(ElectronicMaterial::ACCESS_LEVELS)],
            'license_terms' => ['nullable', 'string', 'max:2000'],
            'allow_download' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'version_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($required && ! $request->hasFile('file') && trim((string) ($validated['external_url'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'external_url' => __('librarian.catalog.materials.source_required'),
            ]);
        }

        // The uploaded file must actually be the kind the librarian declared.
        // Checked here rather than by narrowing FILE_TYPES, so the model keeps
        // supporting every format while a mislabelled upload is still refused —
        // the viewer decides how to render from file_type, so a DOCX filed as
        // "pdf" would reach the reader as a broken page.
        if ($request->hasFile('file')) {
            $mime = (string) $request->file('file')->getMimeType();
            $allowed = self::MIME_BY_FILE_TYPE[$validated['file_type']] ?? [];

            if ($allowed !== [] && ! in_array($mime, $allowed, true)) {
                throw ValidationException::withMessages([
                    'file' => __('librarian.catalog.materials.file_type_mismatch', [
                        'type' => __('librarian.catalog.materials.file_types.'.$validated['file_type']),
                        'mime' => $mime,
                    ]),
                ]);
            }
        }

        $validated['allow_download'] = $request->boolean('allow_download');
        unset($validated['file'], $validated['version_reason'], $validated['is_active']);

        return $validated;
    }

    private function prepareWorkflowDefaults(ElectronicMaterial $material): void
    {
        $material->material_type = match ($material->file_type) {
            'pdf' => 'book_pdf',
            'image' => 'image_collection',
            'presentation' => 'presentation',
            default => 'methodological_material',
        };
        $material->workflow_status = 'uploaded';
        $material->copyright_status = 'unknown';
        $material->preview_policy = 'none';
        $material->download_policy = 'disabled';
        $material->print_policy = 'disabled';
        $material->copy_policy = 'disabled';
        $material->is_active = false;
    }

    private function assertBelongsTo(ElectronicMaterial $material, BibliographicRecord $record): void
    {
        abort_unless($material->bibliographic_record_id === $record->getKey(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function materialSnapshot(ElectronicMaterial $material): array
    {
        return $material->only([
            'title', 'file_path', 'external_url', 'file_type', 'file_size',
            'access_level', 'license_terms', 'allow_download', 'is_active',
        ]);
    }
}
