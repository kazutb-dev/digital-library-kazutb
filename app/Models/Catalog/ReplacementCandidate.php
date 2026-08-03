<?php

namespace App\Models\Catalog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplacementCandidate extends Model
{
    public const STATUSES = ['proposed', 'under_review', 'approved', 'rejected', 'clarification_requested'];

    public const REQUIRED_CRITERIA = [
        'work_matches', 'title_matches_or_equivalent', 'author_matches_or_approved',
        'content_matches', 'academic_purpose_matches', 'usable_condition',
        'no_serious_damage', 'not_library_copy',
    ];

    protected $fillable = [
        'incident_case_id', 'bibliographic_record_id', 'isbn', 'author', 'title',
        'work_title', 'publisher', 'publication_year', 'language', 'resource_type',
        'udc_code', 'content_description', 'copy_condition', 'estimated_value',
        'source', 'proposed_by', 'status', 'work_matches', 'title_matches_or_equivalent',
        'author_matches_or_approved', 'content_matches', 'academic_purpose_matches',
        'usable_condition', 'no_serious_damage', 'not_library_copy', 'isbn_matches',
        'publisher_matches', 'year_difference', 'year_within_tolerance', 'language_matches',
        'resource_type_matches', 'value_comparable', 'complete_set', 'match_score',
        'exception_criteria', 'reviewer_comment', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer', 'estimated_value' => 'decimal:2',
            'year_difference' => 'integer', 'match_score' => 'integer',
            'reviewed_at' => 'datetime', 'exception_criteria' => 'array',
            'work_matches' => 'boolean', 'title_matches_or_equivalent' => 'boolean',
            'author_matches_or_approved' => 'boolean', 'content_matches' => 'boolean',
            'academic_purpose_matches' => 'boolean', 'usable_condition' => 'boolean',
            'no_serious_damage' => 'boolean', 'not_library_copy' => 'boolean',
            'isbn_matches' => 'boolean', 'publisher_matches' => 'boolean',
            'year_within_tolerance' => 'boolean', 'language_matches' => 'boolean',
            'resource_type_matches' => 'boolean', 'value_comparable' => 'boolean',
            'complete_set' => 'boolean',
        ];
    }

    public function incidentCase(): BelongsTo
    {
        return $this->belongsTo(CirculationIncidentCase::class, 'incident_case_id');
    }

    public function bibliographicRecord(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class);
    }

    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function failedRequiredCriteria(): array
    {
        return array_values(array_filter(self::REQUIRED_CRITERIA, fn (string $criterion): bool => $this->{$criterion} !== true));
    }
}
