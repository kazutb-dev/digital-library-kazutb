<?php

namespace App\Models;

use App\Models\Catalog\BibliographicRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodicalSubscription extends Model
{
    protected $fillable = ['bibliographic_record_id', 'title_snapshot', 'year', 'expected_issues', 'branch_id', 'fund_id', 'shelf', 'status'];

    public function issues(): HasMany
    {
        return $this->hasMany(PeriodicalIssue::class);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class, 'bibliographic_record_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }
}
