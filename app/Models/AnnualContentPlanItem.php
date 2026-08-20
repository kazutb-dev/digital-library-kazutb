<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualContentPlanItem extends Model
{
    public const STATUSES = ['planned', 'preparing', 'announced', 'completed', 'postponed', 'cancelled'];

    protected $fillable = ['plan_id', 'item_number', 'type', 'title_kk', 'title_ru', 'title_en', 'planned_date', 'faculty', 'department', 'branch_id', 'responsible_id', 'audience', 'expected_result', 'status', 'publication_id', 'actual_date', 'completion_report', 'result_files', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['planned_date' => 'immutable_date', 'actual_date' => 'immutable_date', 'result_files' => 'array'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AnnualContentPlan::class, 'plan_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(News::class, 'publication_id');
    }
}
