<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BibliographyFormatter;
use App\Services\ShortlistStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortlistController extends Controller
{
    public function __construct(
        private readonly ShortlistStorageService $storage,
    ) {}

    /**
     * List all shortlisted items.
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->storage->getItems($request);

        return response()->json([
            'data' => array_values($items),
            'meta' => [
                'total' => count($items),
            ],
        ]);
    }

    /**
     * Add a book/resource to the shortlist.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:1000'],
            'type' => ['nullable', 'string', 'in:book,external_resource'],
            'author' => ['nullable', 'string', 'max:500'],
            'publisher' => ['nullable', 'string', 'max:500'],
            'year' => ['nullable', 'string', 'max:10'],
            'language' => ['nullable', 'string', 'max:50'],
            'isbn' => ['nullable', 'string', 'max:30'],
            'available' => ['nullable', 'integer', 'min:0'],
            'total' => ['nullable', 'integer', 'min:0'],
            'url' => ['nullable', 'string', 'url', 'max:2048'],
            'provider' => ['nullable', 'string', 'max:500'],
            'access_type' => ['nullable', 'string', 'max:50'],
        ]);

        [$item, $isNew] = $this->storage->addItem($request, $validated);

        if (! $isNew) {
            return response()->json([
                'message' => 'Ресурс уже в подборке.',
                'duplicate' => true,
                'data' => $item,
            ], 409);
        }

        return response()->json([
            'message' => 'Ресурс добавлен в подборку.',
            'data' => $item,
            'meta' => ['total' => $this->storage->countItems($request)],
        ], 201);
    }

    /**
     * Remove a book/resource from the shortlist.
     */
    public function destroy(Request $request, string $identifier): JsonResponse
    {
        $removed = $this->storage->removeItem($request, $identifier);

        if (! $removed) {
            return response()->json([
                'message' => 'Книга не найдена в подборке.',
            ], 404);
        }

        return response()->json([
            'message' => 'Книга удалена из подборки.',
            'meta' => ['total' => $this->storage->countItems($request)],
        ]);
    }

    /**
     * Clear the entire shortlist.
     */
    public function clear(Request $request): JsonResponse
    {
        $this->storage->clearItems($request);

        return response()->json([
            'message' => 'Подборка очищена.',
            'meta' => ['total' => 0],
        ]);
    }

    /**
     * Export shortlist as formatted bibliography text.
     */
    public function export(Request $request): JsonResponse
    {
        $format = $request->query('format', BibliographyFormatter::FORMAT_NUMBERED);
        $items = $this->storage->getItems($request);
        $formatter = new BibliographyFormatter();

        $result = $formatter->format(array_values($items), $format);

        return response()->json([
            'data' => $result,
            'meta' => ['total' => count($items)],
        ]);
    }

    /**
     * Lightweight summary for account/workbench display.
     */
    public function summary(Request $request): JsonResponse
    {
        $items = $this->storage->getItems($request);
        $draft = $this->storage->getDraftMeta($request);

        $books = 0;
        $external = 0;
        $lastAddedAt = null;

        foreach ($items as $item) {
            if (($item['type'] ?? 'book') === 'external_resource') {
                $external++;
            } else {
                $books++;
            }
            $addedAt = $item['addedAt'] ?? null;
            if ($addedAt !== null && ($lastAddedAt === null || $addedAt > $lastAddedAt)) {
                $lastAddedAt = $addedAt;
            }
        }

        return response()->json([
            'data' => [
                'total' => count($items),
                'books' => $books,
                'external' => $external,
                'lastAddedAt' => $lastAddedAt,
                'draft' => $draft,
            ],
        ]);
    }

    /**
     * Update draft title/notes metadata.
     */
    public function updateDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $draft = $this->storage->updateDraftMeta(
            $request,
            $validated['title'] ?? null,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Данные черновика обновлены.',
            'data' => $draft,
        ]);
    }

    /**
     * Check shortlist status for given identifiers.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifiers' => ['required', 'array', 'max:50'],
            'identifiers.*' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->storage->checkIdentifiers($request, $validated['identifiers']);

        return response()->json([
            'data' => $result,
            'meta' => ['total' => $this->storage->countItems($request)],
        ]);
    }
}
