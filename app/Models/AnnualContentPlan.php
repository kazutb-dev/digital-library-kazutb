<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualContentPlan extends Model
{
    public const STATUSES = ['draft', 'pending_approval', 'approved', 'active', 'completed', 'archived'];

    protected $fillable = ['year', 'title', 'status', 'created_by', 'approved_by', 'approved_at', 'notes'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'approved_at' => 'immutable_datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AnnualContentPlanItem::class, 'plan_id');
    }
}
