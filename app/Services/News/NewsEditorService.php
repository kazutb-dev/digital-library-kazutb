<?php

namespace App\Services\News;

use App\Models\Catalog\RepositoryItem;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\NewsSlugRedirect;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NewsEditorService
{
    public function __construct(private NewsContentSanitizer $sanitizer, private NewsMediaService $media, private NewsWorkflowService $workflow, private AuditLogger $audit) {}

    public function save(Request $request, User $actor, ?News $news = null): News
    {
        $this->assertMayEdit($actor, $news);
        if (! Schema::hasColumn('news', 'type')) {
            return $this->saveLegacy($request, $actor, $news);
        }
        $request->merge([
            'type' => $request->input('type', $request->input('category')),
            'title_kk' => $request->input('title_kk', $request->input('title')),
            'content_kk' => $request->input('content_kk', $request->input('body')),
            'excerpt_kk' => $request->input('excerpt_kk', $request->input('excerpt')),
        ]);
        $data = $request->validate($this->rules($news));
        if (! Schema::hasColumn('news', 'repository_item_id')) {
            unset($data['repository_item_id']);
        } elseif (! empty($data['repository_item_id'])
            && ! RepositoryItem::query()->publicMetadata()->whereKey($data['repository_item_id'])->exists()) {
            throw ValidationException::withMessages([
                'repository_item_id' => __('news.validation.repository_not_public'),
            ]);
        }
        $this->assertCategorySupportsType($data);
        $data['importance'] = $data['importance'] ?? $news?->importance ?? 'normal';
        $data['visibility'] = $data['visibility'] ?? $news?->visibility ?? 'public';
        $data['timezone'] = $data['timezone'] ?? $news?->timezone ?? 'Asia/Almaty';
        $data['homepage_priority'] = $data['homepage_priority'] ?? $news?->homepage_priority ?? 0;
        foreach (News::LANGUAGES as $locale) {
            $data['content_'.$locale] = $this->sanitizer->sanitize($data['content_'.$locale] ?? null);
        }
        $data['show_on_homepage'] = $actor->can('news.manage_homepage') ? $request->boolean('show_on_homepage') : (bool) ($news?->show_on_homepage ?? false);
        $data['is_featured'] = $actor->can('news.manage_homepage') ? $request->boolean('is_featured') : (bool) ($news?->is_featured ?? false);
        $data['is_pinned'] = $actor->can('news.manage_homepage') ? $request->boolean('is_pinned') : (bool) ($news?->is_pinned ?? false);
        $data['registration_required'] = $request->boolean('registration_required');
        $data['gallery_enabled'] = $request->boolean('gallery_enabled');
        $data['title'] = $data['title_kk'];
        $data['excerpt'] = $data['excerpt_kk'] ?? null;
        $data['body'] = $data['content_kk'] ?? '';
        $data['category'] = $data['type'];
        $data['language'] = 'kk';
        foreach (News::LANGUAGES as $locale) {
            $title = trim((string) ($data['title_'.$locale] ?? ''));
            if ($title !== '' && trim((string) ($data['slug_'.$locale] ?? '')) === '') {
                $data['slug_'.$locale] = $this->uniqueSlug($title, $locale, $news);
            }
        }
        $data['slug'] = $data['slug_kk'] ?: $this->uniqueSlug($data['title_kk'], 'kk', $news);
        $newCover = null;
        if ($request->hasFile('cover_image')) {
            $paths = $this->media->store($request->file('cover_image'));
            $newCover = $paths['original'];
            $data['cover_image'] = $newCover;
        }
        try {
            return DB::transaction(function () use ($data, $actor, $news, $newCover): News {
                if (! $news) {
                    $data['created_by'] = $actor->getKey();
                    $data['status'] = 'draft';
                    $saved = News::query()->create($data);
                    $this->workflow->recordRevision($saved, $actor, $this->snapshot($saved), 'created');
                    $this->audit->logRequired(actionType: 'news.created', entityType: 'news', entityId: $saved->getKey(), newValues: $this->snapshot($saved), scope: 'operational');

                    return $saved;
                }
                $locked = News::query()->whereKey($news)->lockForUpdate()->firstOrFail();
                $this->assertMayEdit($actor, $locked);
                if (in_array($locked->status, ['published', 'archived', 'cancelled'], true)) {
                    throw ValidationException::withMessages(['status' => __('news.validation.immutable_published')]);
                }
                $before = $this->snapshot($locked);
                foreach (News::LANGUAGES as $locale) {
                    $key = 'slug_'.$locale;
                    if ($locked->status === 'published' && $locked->{$key} && isset($data[$key]) && $locked->{$key} !== $data[$key]) {
                        NewsSlugRedirect::query()->firstOrCreate(['locale' => $locale, 'old_slug' => $locked->{$key}], ['news_id' => $locked->getKey()]);
                    }
                }
                $oldCover = $locked->cover_image;
                $locked->update($data);
                $this->workflow->recordRevision($locked, $actor, $this->snapshot($locked), 'content_updated');
                $this->audit->logRequired(actionType: 'news.updated', entityType: 'news', entityId: $locked->getKey(), oldValues: $before, newValues: $this->snapshot($locked), scope: 'operational');
                if ($newCover && $oldCover && $oldCover !== $newCover) {
                    $this->media->deleteIfUnused($oldCover);
                }

                return $locked->refresh();
            });
        } catch (\Throwable $exception) {
            if ($newCover) {
                $this->media->deleteIfUnused($newCover);
            } throw $exception;
        }
    }

    /** @return array<string,mixed> */
    private function rules(?News $news): array
    {
        $id = $news?->getKey();

        return [
            'type' => ['required', Rule::in(News::TYPES)], 'category_id' => ['nullable', 'integer', Rule::exists('news_categories', 'id')->where('active', true)],
            'title_kk' => ['required', 'string', 'max:255'], 'title_ru' => ['nullable', 'string', 'max:255'], 'title_en' => ['nullable', 'string', 'max:255'],
            'excerpt_kk' => ['nullable', 'string', 'max:1500'], 'excerpt_ru' => ['nullable', 'string', 'max:1500'], 'excerpt_en' => ['nullable', 'string', 'max:1500'],
            'content_kk' => ['nullable', 'string', 'max:150000'], 'content_ru' => ['nullable', 'string', 'max:150000'], 'content_en' => ['nullable', 'string', 'max:150000'],
            'slug_kk' => ['nullable', 'alpha_dash:ascii', 'max:255', Rule::unique('news', 'slug_kk')->ignore($id)], 'slug_ru' => ['nullable', 'alpha_dash:ascii', 'max:255', Rule::unique('news', 'slug_ru')->ignore($id)], 'slug_en' => ['nullable', 'alpha_dash:ascii', 'max:255', Rule::unique('news', 'slug_en')->ignore($id)],
            'image_alt_kk' => ['nullable', 'string', 'max:255'], 'image_alt_ru' => ['nullable', 'string', 'max:255'], 'image_alt_en' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
            'audience' => ['nullable', 'string', 'max:255'], 'branch_id' => ['nullable', 'integer'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'timezone:all'], 'venue' => ['nullable', 'string', 'max:255'], 'venue_kk' => ['nullable', 'string', 'max:255'], 'venue_ru' => ['nullable', 'string', 'max:255'], 'venue_en' => ['nullable', 'string', 'max:255'],
            'online_url' => ['nullable', 'url:http,https', 'max:2048'], 'registration_url' => ['nullable', 'url:http,https', 'max:2048'], 'registration_required' => ['nullable', 'boolean'], 'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'contact_name' => ['nullable', 'string', 'max:255'], 'contact_email' => ['nullable', 'email', 'max:255'], 'contact_phone' => ['nullable', 'string', 'max:64'], 'organizer' => ['nullable', 'string', 'max:255'],
            'annual_plan_item_id' => ['nullable', 'integer', Rule::exists('annual_content_plan_items', 'id')], 'expires_at' => ['nullable', 'date'], 'homepage_until' => ['nullable', 'date'],
            'repository_item_id' => ['nullable', 'integer', Rule::exists('repository_items', 'id')],
            'importance' => ['nullable', Rule::in(['normal', 'important', 'critical'])], 'source' => ['nullable', 'string', 'max:255'], 'visibility' => ['nullable', Rule::in(['public', 'members', 'staff'])],
            'show_on_homepage' => ['nullable', 'boolean'], 'is_featured' => ['nullable', 'boolean'], 'is_pinned' => ['nullable', 'boolean'], 'homepage_priority' => ['nullable', 'integer', 'min:0', 'max:1000'], 'gallery_enabled' => ['nullable', 'boolean'],
            'seo_title_kk' => ['nullable', 'string', 'max:255'], 'seo_title_ru' => ['nullable', 'string', 'max:255'], 'seo_title_en' => ['nullable', 'string', 'max:255'],
            'seo_description_kk' => ['nullable', 'string', 'max:500'], 'seo_description_ru' => ['nullable', 'string', 'max:500'], 'seo_description_en' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function assertMayEdit(User $actor, ?News $news): void
    {
        if (! $news) {
            if (! $actor->can('news.create')) {
                throw new AuthorizationException;
            }

            return;
        }
        if (! $actor->can('news.edit_any') && ! ($actor->can('news.edit_own') && (int) $news->created_by === (int) $actor->getKey())) {
            throw new AuthorizationException;
        }
    }

    /** @param array<string, mixed> $data */
    private function assertCategorySupportsType(array $data): void
    {
        if (empty($data['category_id'])) {
            return;
        }

        $category = NewsCategory::query()->findOrFail($data['category_id']);
        $allowedTypes = $category->allowed_types ?? [];

        if ($allowedTypes !== [] && ! in_array($data['type'], $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'category_id' => __('news.validation.category_type'),
            ]);
        }
    }

    private function uniqueSlug(string $title, string $locale, ?News $news): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'publication';
        $slug = $base;
        $counter = 2;
        while (News::withTrashed()->where('slug_'.$locale, $slug)->when($news, fn ($q) => $q->whereKeyNot($news->getKey()))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /** @return array<string,mixed> */
    private function snapshot(News $news): array
    {
        return $news->only(['type', 'category_id', 'status', 'title_kk', 'title_ru', 'title_en', 'excerpt_kk', 'excerpt_ru', 'excerpt_en', 'content_kk', 'content_ru', 'content_en', 'starts_at', 'ends_at', 'venue', 'audience', 'show_on_homepage', 'is_featured', 'cover_image', 'repository_item_id']);
    }

    private function saveLegacy(Request $request, User $actor, ?News $news): News
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'category' => ['required', Rule::in(News::TYPES)], 'language' => ['required', Rule::in(News::LANGUAGES)], 'body' => ['required', 'string', 'max:100000'], 'excerpt' => ['nullable', 'string', 'max:1000'], 'status' => ['required', Rule::in(['draft', 'scheduled', 'published', 'archived'])], 'publish_at' => ['nullable', 'date'], 'show_on_homepage' => ['required', 'boolean']]);
        if (in_array($data['status'], ['scheduled', 'published'], true) && ! $actor->can('news.publish')) {
            $requestedAt = empty($data['publish_at']) ? null : Carbon::parse($data['publish_at'])->utc()->format('Y-m-d H:i');
            $storedAt = $news?->publish_at?->utc()->format('Y-m-d H:i');
            $controlsChanged = ! $news || $data['status'] !== $news->status || $requestedAt !== $storedAt || $request->boolean('show_on_homepage') !== (bool) $news->show_on_homepage;
            if ($controlsChanged) {
                throw new AuthorizationException;
            }
        }
        if ($data['status'] === 'published' && ! empty($data['publish_at']) && now()->lt($data['publish_at'])) {
            throw ValidationException::withMessages(['publish_at' => __('news.validation.published_not_future')]);
        }
        $data['show_on_homepage'] = $request->boolean('show_on_homepage');

        return DB::transaction(function () use ($data, $actor, $news) {
            if (! $news) {
                $data['slug'] = Str::slug($data['title']) ?: 'news';
                $data['created_by'] = $actor->getKey();
                if ($data['status'] === 'published') {
                    $data['published_by'] = $actor->getKey();
                    $data['publish_at'] ??= now();
                }$saved = News::query()->create($data);
                $this->audit->logRequired(actionType: $saved->status === 'published' ? 'publish' : 'create', entityType: 'news', entityId: $saved->getKey(), newValues: $saved->only(['title', 'status']), scope: 'operational');

                return $saved;
            }
            if (! $actor->can('news.publish')) {
                $data['status'] = $news->status;
                $data['publish_at'] = $news->publish_at;
                $data['show_on_homepage'] = $news->show_on_homepage;
            }
            $before = $news->only(['title', 'status']);
            $news->update($data);
            $this->audit->logRequired(actionType: 'update', entityType: 'news', entityId: $news->getKey(), oldValues: $before, newValues: $news->only(['title', 'status']), scope: 'operational');

            return $news->refresh();
        });
    }
}
