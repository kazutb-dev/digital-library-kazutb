<?php

namespace App\Models\Catalog;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopyTransfer extends Model
{
    public const STATUSES = ['requested', 'approved', 'in_transit', 'received', 'cancelled', 'failed'];

    public const OPEN_STATUSES = ['requested', 'approved', 'in_transit'];

    protected $fillable = [
        'transfer_number', 'copy_id', 'reservation_id', 'source_branch_id',
        'destination_branch_id', 'status', 'requested_by', 'approved_by', 'sent_by',
        'received_by', 'requested_at', 'approved_at', 'sent_at', 'received_at',
        'expected_at', 'actual_duration_minutes', 'notes', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime', 'approved_at' => 'datetime', 'sent_at' => 'datetime',
            'received_at' => 'datetime', 'expected_at' => 'datetime', 'actual_duration_minutes' => 'integer',
        ];
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
