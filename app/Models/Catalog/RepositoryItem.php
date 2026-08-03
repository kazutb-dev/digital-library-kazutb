<?php

namespace App\Models\Catalog;

use App\Models\User;
use Database\Factories\Catalog\RepositoryItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryItem extends Model
{
    /** @use HasFactory<RepositoryItemFactory> */
    use HasFactory;

    public const WORK_TYPES = [
        'thesis', 'master_thesis', 'phd_dissertation', 'article', 'report', 'publication', 'abstract',
    ];

    public const STATUSES = ['draft', 'under_review', 'approved', 'rejected', 'published', 'archived'];

    protected $fillable = [
        'title', 'authors', 'work_type', 'year', 'department', 'udc_code',
        'abstract', 'keywords', 'language', 'file_path', 'file_name', 'file_size',
        'status', 'uploaded_by', 'reviewed_by', 'approved_by', 'review_notes', 'published_at',
    ];

    protected static function newFactory(): RepositoryItemFactory
    {
        return RepositoryItemFactory::new();
    }

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'keywords' => 'array',
            'year' => 'integer',
            'file_size' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = '%'.mb_strtolower(trim($term)).'%';

        return $query->where(function (Builder $builder) use ($needle): void {
            $builder
                ->whereRaw('LOWER(title) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(authors AS TEXT)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(abstract, \'\')) LIKE ?', [$needle]);
        });
    }
}
