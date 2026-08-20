<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean', 'capabilities' => 'array', 'data_policy' => 'array',
            'last_health_check_at' => 'immutable_datetime', 'last_success_at' => 'immutable_datetime',
            'last_failure_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function inboxMessages(): HasMany
    {
        return $this->hasMany(IntegrationInboxMessage::class);
    }

    public function outboxMessages(): HasMany
    {
        return $this->hasMany(IntegrationOutboxMessage::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(IntegrationSyncRun::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(IntegrationConflict::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(IntegrationMapping::class);
    }
}
