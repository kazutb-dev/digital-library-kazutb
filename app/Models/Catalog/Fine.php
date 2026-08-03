<?php

namespace App\Models\Catalog;

use App\Models\User;
use Database\Factories\Catalog\FineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Fine extends Model
{
    /** @use HasFactory<FineFactory> */
    use HasFactory;

    public const REASONS = ['overdue', 'lost', 'damaged'];

    public const STATUSES = ['pending', 'paid', 'waived'];

    protected $fillable = [
        'user_id', 'loan_id', 'copy_id', 'amount', 'reason', 'status',
        'charged_at', 'resolved_at', 'resolved_by', 'notes',
    ];

    protected static function newFactory(): FineFactory
    {
        return FineFactory::new();
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'charged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'copy_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function incidentCase(): HasOne
    {
        return $this->hasOne(CirculationIncidentCase::class);
    }
}
