<?php

namespace App\Models\Catalog;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CirculationIncidentCase extends Model
{
    public const STATUSES = ['open', 'awaiting_reader', 'replacement_proposed', 'under_review', 'approved', 'rejected', 'awaiting_registration', 'resolved', 'cancelled', 'disputed'];

    public const OPEN_STATUSES = ['open', 'awaiting_reader', 'replacement_proposed', 'under_review', 'approved', 'rejected', 'awaiting_registration', 'disputed'];

    public const DAMAGE_SEVERITIES = ['minor', 'moderate', 'severe', 'irreparable'];

    public const PRELIMINARY_ACTIONS = ['return_to_fund', 'repair', 'fine', 'replacement', 'write_off'];

    public const RESOLUTIONS = ['replacement', 'fine', 'fine_and_replacement', 'repair', 'write_off', 'monetary_compensation', 'no_charge'];

    protected $fillable = [
        'case_number', 'incident_type', 'loan_id', 'original_copy_id', 'reader_id',
        'opened_by', 'assigned_to', 'status', 'damage_severity', 'damage_description',
        'condition_before', 'condition_after', 'preliminary_action', 'resolution_type',
        'fine_id', 'replacement_copy_id', 'opened_at', 'resolution_due_at', 'resolved_at',
        'resolved_by', 'decision_reason', 'notes', 'requires_director', 'fine_remains',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime', 'resolution_due_at' => 'datetime', 'resolved_at' => 'datetime',
            'requires_director' => 'boolean', 'fine_remains' => 'boolean',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function originalCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'original_copy_id');
    }

    public function replacementCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'replacement_copy_id');
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reader_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function fine(): BelongsTo
    {
        return $this->belongsTo(Fine::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ReplacementCandidate::class, 'incident_case_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class, 'incident_case_id');
    }
}
