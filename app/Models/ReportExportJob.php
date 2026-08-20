<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Asynchronous materialisation of one approved immutable report snapshot. */
class ReportExportJob extends Model
{
    public const STATUSES = ['queued', 'generating', 'ready', 'failed'];

    public const FORMATS = ['csv', 'pdf', 'xlsx', 'docx'];

    protected $table = 'report_export_jobs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'attempts' => 'integer',
            'file_size' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'retention_until' => 'immutable_datetime',
            'lease_expires_at' => 'immutable_datetime',
            'dispatch_after' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
            'last_heartbeat_at' => 'immutable_datetime',
            'file_deleted_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(OfficialReportSnapshot::class, 'snapshot_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
