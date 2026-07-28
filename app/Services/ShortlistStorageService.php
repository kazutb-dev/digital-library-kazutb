<?php

namespace App\Services;

use App\Models\LiteratureDraft;
use App\Models\LiteratureDraftItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Abstracts shortlist storage: session for guests, DB for authenticated users.
 *
 * When an authenticated user accesses the shortlist for the first time in a
 * session, any existing session items are merged into the persistent DB draft
 * (deduplicating by identifier). Subsequent reads/writes go directly to DB.
 */
class ShortlistStorageService
{
    /**
     * Get the authenticated user ID from the session, or null for guests.
     */
    public function getUserId(Request $request): ?string
    {
        $user = $request->session()->get('library.user');

        if (is_array($user) && isset($user['id']) && $user['id'] !== '') {
            return (string) $user['id'];
        }

        return null;
    }

    /**
     * Get all shortlist items as an identifier-keyed array.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getItems(Request $request): array
    {
        $userId = $this->getUserId($request);

        if ($userId === null) {
            return $this->getSessionItems($request);
        }

        $this->migrateSessionToDb($request, $userId);

        return $this->getDbItems($userId);
    }

    /**
     * Add an item to the shortlist. Returns [item, isNew].
     *
     * @param array<string, mixed> $data Validated item data (without addedAt)
     * @return array{0: array<string, mixed>, 1: bool}
     */
    public function addItem(Request $request, array $data): array
    {
        $userId = $this->getUserId($request);
        $identifier = $data['identifier'];

        if ($userId === null) {
            return $this->addSessionItem($request, $data);
        }

        $this->migrateSessionToDb($request, $userId);

        $draft = $this->getOrCreateDraft($userId);
        $existing = $draft->items()->where('identifier', $identifier)->first();

        if ($existing) {
            return [$existing->toShortlistArray(), false];
        }

        $item = $draft->items()->create([
            'identifier' => $identifier,
            'title' => $data['title'],
            'type' => $data['type'] ?? 'book',
            'author' => $data['author'] ?? null,
            'publisher' => $data['publisher'] ?? null,
            'year' => $data['year'] ?? null,
            'language' => $data['language'] ?? null,
            'isbn' => $data['isbn'] ?? null,
            'available' => $data['available'] ?? null,
            'total' => $data['total'] ?? null,
            'url' => $data['url'] ?? null,
            'provider' => $data['provider'] ?? null,
            'access_type' => $data['access_type'] ?? null,
            'added_at' => now(),
        ]);

        return [$item->toShortlistArray(), true];
    }

    /**
     * Remove an item by identifier. Returns true if removed.
     */
    public function removeItem(Request $request, string $identifier): bool
    {
        $userId = $this->getUserId($request);

        if ($userId === null) {
            return $this->removeSessionItem($request, $identifier);
        }

        $this->migrateSessionToDb($request, $userId);

        $draft = LiteratureDraft::where('user_id', $userId)->first();

        if (! $draft) {
            return false;
        }

        $deleted = $draft->items()->where('identifier', $identifier)->delete();

        return $deleted > 0;
    }

    /**
     * Clear all items.
     */
    public function clearItems(Request $request): void
    {
        $userId = $this->getUserId($request);

        if ($userId === null) {
            $request->session()->put('library.shortlist', []);

            return;
        }

        $this->migrateSessionToDb($request, $userId);

        $draft = LiteratureDraft::where('user_id', $userId)->first();

        if ($draft) {
            $draft->items()->delete();
        }
    }

    /**
     * Get draft metadata (title, notes, updatedAt).
     *
     * @return array{title: ?string, notes: ?string, updatedAt: ?string, persistent: bool}
     */
    public function getDraftMeta(Request $request): array
    {
        $userId = $this->getUserId($request);

        if ($userId === null) {
            $draft = $request->session()->get('library.shortlist_draft', []);

            return [
                'title' => $draft['title'] ?? null,
                'notes' => $draft['notes'] ?? null,
                'updatedAt' => $draft['updatedAt'] ?? null,
                'persistent' => false,
            ];
        }

        $this->migrateSessionToDb($request, $userId);

        $draft = LiteratureDraft::where('user_id', $userId)->first();

        return [
            'title' => $draft?->title,
            'notes' => $draft?->notes,
            'updatedAt' => $draft?->updated_at?->toIso8601String(),
            'persistent' => true,
        ];
    }

    /**
     * Update draft metadata.
     *
     * @return array{title: ?string, notes: ?string, updatedAt: ?string, persistent: bool}
     */
    public function updateDraftMeta(Request $request, ?string $title, ?string $notes): array
    {
        $userId = $this->getUserId($request);

        if ($userId === null) {
            $draft = $request->session()->get('library.shortlist_draft', []);
            $draft['title'] = $title ?? $draft['title'] ?? null;
            $draft['notes'] = $notes ?? $draft['notes'] ?? null;
            $draft['updatedAt'] = now()->toIso8601String();
            $request->session()->put('library.shortlist_draft', $draft);

            return [
                'title' => $draft['title'],
                'notes' => $draft['notes'],
                'updatedAt' => $draft['updatedAt'],
                'persistent' => false,
            ];
        }

        $this->migrateSessionToDb($request, $userId);

        $draft = $this->getOrCreateDraft($userId);
        if ($title !== null) {
            $draft->title = $title;
        }
        if ($notes !== null) {
            $draft->notes = $notes;
        }
        $draft->save();

        return [
            'title' => $draft->title,
            'notes' => $draft->notes,
            'updatedAt' => $draft->updated_at?->toIso8601String(),
            'persistent' => true,
        ];
    }

    /**
     * Check which identifiers exist in the shortlist.
     *
     * @param string[] $identifiers
     * @return array<string, bool>
     */
    public function checkIdentifiers(Request $request, array $identifiers): array
    {
        $items = $this->getItems($request);
        $result = [];

        foreach ($identifiers as $id) {
            $result[$id] = isset($items[$id]);
        }

        return $result;
    }

    /**
     * Count items.
     */
    public function countItems(Request $request): int
    {
        return count($this->getItems($request));
    }

    // ── Session storage (guest) ───────────────────────────────────

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getSessionItems(Request $request): array
    {
        $list = $request->session()->get('library.shortlist', []);

        return is_array($list) ? $list : [];
    }

    /**
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function addSessionItem(Request $request, array $data): array
    {
        $items = $this->getSessionItems($request);
        $identifier = $data['identifier'];

        if (isset($items[$identifier])) {
            return [$items[$identifier], false];
        }

        $item = [
            'identifier' => $identifier,
            'title' => $data['title'],
            'type' => $data['type'] ?? 'book',
            'author' => $data['author'] ?? null,
            'publisher' => $data['publisher'] ?? null,
            'year' => $data['year'] ?? null,
            'language' => $data['language'] ?? null,
            'isbn' => $data['isbn'] ?? null,
            'available' => $data['available'] ?? null,
            'total' => $data['total'] ?? null,
            'url' => $data['url'] ?? null,
            'provider' => $data['provider'] ?? null,
            'access_type' => $data['access_type'] ?? null,
            'addedAt' => now()->toIso8601String(),
        ];

        $items[$identifier] = $item;
        $request->session()->put('library.shortlist', $items);

        return [$item, true];
    }

    private function removeSessionItem(Request $request, string $identifier): bool
    {
        $items = $this->getSessionItems($request);

        if (! isset($items[$identifier])) {
            return false;
        }

        unset($items[$identifier]);
        $request->session()->put('library.shortlist', $items);

        return true;
    }

    // ── DB storage (authenticated) ────────────────────────────────

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getDbItems(string $userId): array
    {
        $draft = LiteratureDraft::where('user_id', $userId)->first();

        if (! $draft) {
            return [];
        }

        $result = [];

        foreach ($draft->items as $item) {
            $result[$item->identifier] = $item->toShortlistArray();
        }

        return $result;
    }

    private function getOrCreateDraft(string $userId): LiteratureDraft
    {
        return LiteratureDraft::firstOrCreate(
            ['user_id' => $userId],
            ['title' => null, 'notes' => null],
        );
    }

    // ── Session → DB migration ────────────────────────────────────

    /**
     * Migrate session shortlist items and draft metadata into the DB.
     *
     * Runs once per session (flagged by library.shortlist_migrated).
     * Merges items by identifier — existing DB items take precedence.
     * After migration, session data is cleared.
     */
    private function migrateSessionToDb(Request $request, string $userId): void
    {
        if ($request->session()->get('library.shortlist_migrated')) {
            return;
        }

        $request->session()->put('library.shortlist_migrated', true);

        $sessionItems = $request->session()->get('library.shortlist', []);
        $sessionDraft = $request->session()->get('library.shortlist_draft', []);

        if (! is_array($sessionItems)) {
            $sessionItems = [];
        }

        if (empty($sessionItems) && empty($sessionDraft)) {
            return;
        }

        $draft = $this->getOrCreateDraft($userId);

        // Merge draft metadata (session → DB only if DB is empty)
        if (is_array($sessionDraft) && ! empty($sessionDraft)) {
            if ($draft->title === null && isset($sessionDraft['title'])) {
                $draft->title = $sessionDraft['title'];
            }
            if ($draft->notes === null && isset($sessionDraft['notes'])) {
                $draft->notes = $sessionDraft['notes'];
            }
            $draft->save();
        }

        // Merge items (skip duplicates)
        if (! empty($sessionItems)) {
            $existingIdentifiers = $draft->items()->pluck('identifier')->toArray();
            $toInsert = [];
            $now = now();

            foreach ($sessionItems as $item) {
                if (! is_array($item) || ! isset($item['identifier'])) {
                    continue;
                }

                if (in_array($item['identifier'], $existingIdentifiers, true)) {
                    continue;
                }

                $existingIdentifiers[] = $item['identifier'];

                $toInsert[] = [
                    'draft_id' => $draft->id,
                    'identifier' => $item['identifier'],
                    'title' => (string) ($item['title'] ?? ''),
                    'type' => $item['type'] ?? 'book',
                    'author' => $item['author'] ?? null,
                    'publisher' => $item['publisher'] ?? null,
                    'year' => $item['year'] ?? null,
                    'language' => $item['language'] ?? null,
                    'isbn' => $item['isbn'] ?? null,
                    'available' => isset($item['available']) ? (int) $item['available'] : null,
                    'total' => isset($item['total']) ? (int) $item['total'] : null,
                    'url' => $item['url'] ?? null,
                    'provider' => $item['provider'] ?? null,
                    'access_type' => $item['access_type'] ?? null,
                    'added_at' => isset($item['addedAt']) ? Carbon::parse($item['addedAt']) : $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($toInsert)) {
                LiteratureDraftItem::insert($toInsert);

                Log::info('Shortlist session→DB migration', [
                    'user_id' => $userId,
                    'migrated_items' => count($toInsert),
                    'skipped_duplicates' => count($sessionItems) - count($toInsert),
                ]);
            }
        }

        // Clear session shortlist data
        $request->session()->forget('library.shortlist');
        $request->session()->forget('library.shortlist_draft');
    }
}
