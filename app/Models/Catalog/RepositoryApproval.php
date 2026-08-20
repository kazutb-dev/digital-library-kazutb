<?php

namespace App\Models\Catalog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable evidence of a director's approval of one exact repository version.
 *
 * A later edit never rewrites this row. It clears repository_items.active_approval_id
 * and sends the item through review again, preserving the former decision as audit
 * evidence without allowing it to authorise changed content.
 */
class RepositoryApproval extends Model
{
    protected $fillable = [
        'repository_item_id', 'repository_item_version_id', 'approver_id',
        'approver_role_snapshot', 'checksum_sha256', 'metadata_fingerprint',
        'approved_at',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Repository approval evidence is immutable.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Repository approval evidence is immutable.');
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RepositoryItem::class, 'repository_item_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(RepositoryItemVersion::class, 'repository_item_version_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
