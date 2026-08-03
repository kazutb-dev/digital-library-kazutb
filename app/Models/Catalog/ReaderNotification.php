<?php

namespace App\Models\Catalog;

use App\Models\User;
use App\Support\LocalizedText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;

/**
 * In-app notification delivered to a user's cabinet. Channel eligibility is
 * decided by the admin-managed notification_settings matrix at send time.
 */
class ReaderNotification extends Model
{
    protected $fillable = [
        'user_id', 'event_type', 'title', 'body', 'payload', 'read_at', 'channel',
        'delivery_status', 'idempotency_key', 'attempts', 'last_error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function localizedTitle(): string
    {
        return $this->localizedValue('title_key', (string) $this->title);
    }

    public function localizedBody(): ?string
    {
        if ($this->body === null) {
            return null;
        }

        return $this->localizedValue('body_key', (string) $this->body);
    }

    /** Legacy rows without _i18n deliberately keep their original text. */
    private function localizedValue(string $keyName, string $legacy): string
    {
        $metadata = data_get($this->payload, '_i18n');
        $key = is_array($metadata) ? ($metadata[$keyName] ?? null) : null;
        if (! is_string($key) || $key === '' || ! Lang::has($key)) {
            return $legacy;
        }

        $parameters = $metadata['parameters'] ?? [];

        return __($key, is_array($parameters) ? LocalizedText::parameters($parameters) : []);
    }
}
