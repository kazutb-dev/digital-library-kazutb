<?php

namespace App\Models\Catalog;

use App\Models\User;
use Database\Factories\Catalog\LoanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    public const STATUSES = ['active', 'overdue', 'returned', 'lost'];

    public const OPEN_STATUSES = ['active', 'overdue'];

    public const RETURN_CONDITIONS = ['unchanged', 'normal_wear', 'minor_damage', 'moderate_damage', 'severe_damage', 'irreparable', 'lost'];

    protected $fillable = [
        'user_id', 'copy_id', 'status', 'issued_at', 'due_at', 'returned_at',
        'renewal_count', 'issued_by', 'returned_to', 'condition_on_return', 'notes',
    ];

    protected static function newFactory(): LoanFactory
    {
        return LoanFactory::new();
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'renewal_count' => 'integer',
        ];
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'copy_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function fines(): HasMany
    {
        return $this->hasMany(Fine::class);
    }

    public function incidentCase(): HasOne
    {
        return $this->hasOne(CirculationIncidentCase::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES)->whereNull('returned_at');
    }

    public function isOverdue(): bool
    {
        return $this->returned_at === null && $this->due_at !== null && $this->due_at->isPast();
    }

    public function overdueDays(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->due_at->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function daysRemaining(): int
    {
        if ($this->returned_at !== null || $this->due_at === null) {
            return 0;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_at->startOfDay(), false);
    }
}
