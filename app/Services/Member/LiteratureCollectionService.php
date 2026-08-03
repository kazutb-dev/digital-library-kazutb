<?php

namespace App\Services\Member;

use App\Models\Catalog\BibliographicRecord;
use App\Models\LiteratureCollection;
use App\Models\LiteratureCollectionItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LiteratureCollectionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(User $owner, array $data): LiteratureCollection
    {
        return DB::transaction(function () use ($owner, $data): LiteratureCollection {
            $collection = LiteratureCollection::query()->create([
                'user_id' => (string) $owner->getKey(),
                'created_by' => $owner->getKey(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'slug' => $this->slug($data['title']),
                'collection_type' => $data['collection_type'] ?? 'personal',
                'visibility' => 'private',
                'status' => 'published',
                'owner_type' => 'reader',
                'language' => $owner->locale,
            ]);
            $this->audit->logRequired('collection.created', 'literature_collection', $collection->getKey(), newValues: ['title' => $collection->title, 'type' => $collection->collection_type], scope: 'personal', actor: $owner);

            return $collection;
        });
    }

    public function update(LiteratureCollection $collection, User $owner, array $data): LiteratureCollection
    {
        $this->own($collection, $owner);
        $old = $collection->only(['title', 'description']);
        $collection->fill($data)->save();
        $this->audit->logRequired('collection.updated', 'literature_collection', $collection->getKey(), oldValues: $old, newValues: $collection->only(['title', 'description']), scope: 'personal', actor: $owner);

        return $collection;
    }

    public function add(LiteratureCollection $collection, User $owner, BibliographicRecord $record, ?string $reason = null): LiteratureCollectionItem
    {
        $this->own($collection, $owner);

        return DB::transaction(function () use ($collection, $owner, $record, $reason): LiteratureCollectionItem {
            $item = LiteratureCollectionItem::query()->firstOrCreate(
                ['draft_id' => $collection->getKey(), 'bibliographic_record_id' => $record->getKey()],
                [
                    'identifier' => (string) ($record->isbn ?: $record->getKey()),
                    'title' => (string) $record->title,
                    'type' => 'book',
                    'author' => $record->primary_author,
                    'publisher' => $record->publisher,
                    'year' => $record->publication_year,
                    'language' => $record->language,
                    'isbn' => $record->isbn,
                    'sort_order' => (int) $collection->items()->max('sort_order') + 1,
                    'inclusion_reason' => $reason,
                    'added_at' => now(),
                ],
            );
            if ($item->wasRecentlyCreated) {
                $this->audit->logRequired('collection.item_added', 'literature_collection', $collection->getKey(), newValues: ['record_id' => $record->getKey()], scope: 'personal', actor: $owner);
            }

            return $item;
        });
    }

    public function remove(LiteratureCollection $collection, LiteratureCollectionItem $item, User $owner): void
    {
        $this->own($collection, $owner);
        if ((int) $item->draft_id !== (int) $collection->getKey()) {
            throw new AuthorizationException;
        }
        $recordId = $item->bibliographic_record_id;
        $item->delete();
        $this->audit->logRequired('collection.item_removed', 'literature_collection', $collection->getKey(), newValues: ['record_id' => $recordId], scope: 'personal', actor: $owner);
    }

    public function reorder(LiteratureCollection $collection, User $owner, array $itemIds): void
    {
        $this->own($collection, $owner);
        $owned = $collection->items()->whereIn('id', $itemIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if (count($owned) !== count(array_unique(array_map('intval', $itemIds)))) {
            throw new AuthorizationException;
        }
        DB::transaction(function () use ($collection, $owner, $itemIds): void {
            foreach (array_values($itemIds) as $position => $id) {
                $collection->items()->whereKey((int) $id)->update(['sort_order' => $position + 1]);
            }
            $this->audit->logRequired('collection.updated', 'literature_collection', $collection->getKey(), newValues: ['order_changed' => true], scope: 'personal', actor: $owner);
        });
    }

    public function delete(LiteratureCollection $collection, User $owner): void
    {
        $this->own($collection, $owner);
        $id = $collection->getKey();
        DB::transaction(function () use ($collection, $owner, $id): void {
            $collection->delete();
            $this->audit->logRequired('collection.deleted', 'literature_collection', $id, reason: 'Deleted by collection owner', scope: 'personal', actor: $owner);
        });
    }

    public function follow(LiteratureCollection $collection, User $reader): void
    {
        abort_unless($collection->status === 'published' && $collection->visibility === 'public', 404);
        $collection->followers()->syncWithoutDetaching([$reader->getKey()]);
        $this->audit->logRequired('collection.followed', 'literature_collection', $collection->getKey(), scope: 'personal', actor: $reader);
    }

    public function copy(LiteratureCollection $source, User $reader): LiteratureCollection
    {
        abort_unless($source->status === 'published' && in_array($source->visibility, ['public', 'unlisted'], true), 404);

        return DB::transaction(function () use ($source, $reader): LiteratureCollection {
            $copy = $this->create($reader, ['title' => $source->title.' — копия', 'description' => $source->description]);
            foreach ($source->items()->with('bibliographicRecord')->orderBy('sort_order')->get() as $item) {
                if ($item->bibliographicRecord !== null) {
                    $this->add($copy, $reader, $item->bibliographicRecord, $item->inclusion_reason);
                }
            }
            $this->audit->logRequired('collection.copied', 'literature_collection', $copy->getKey(), newValues: ['source_collection_id' => $source->getKey()], scope: 'personal', actor: $reader);

            return $copy;
        });
    }

    public function own(LiteratureCollection $collection, User $owner): void
    {
        if ((string) $collection->user_id !== (string) $owner->getKey() || $collection->owner_type !== 'reader') {
            throw new AuthorizationException;
        }
    }

    private function slug(string $title): string
    {
        return Str::slug($title).'-'.Str::lower(Str::random(10));
    }
}
