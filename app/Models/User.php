<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderNotification;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'ad_login',
    'role',
    'password',
    'auth_provider',
    'auth_source',
    'external_id',
    'ad_object_guid',
    'ad_samaccountname',
    'ad_user_principal_name',
    'ad_distinguished_name',
    'ad_last_synced_at',
    'ad_last_login_at',
    'given_name',
    'surname',
    'telephone_number',
    'job_title',
    'employee_id',
    'role_source',
    'department',
    'is_active',
    'last_login_at',
    'locale',
    'deactivated_at',
    'deactivated_by',
    'must_change_password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /** @var list<string> */
    private const WORKSPACE_ROLE_PRECEDENCE = [
        'admin',
        'director',
        'senior_librarian',
        'librarian',
        'acquisitions',
        'cataloguer',
        'bibliographer',
        'member',
    ];

    /**
     * New accounts start with the application interface language unless an
     * explicit preference is supplied by the provisioning flow.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'kk',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'ad_last_synced_at' => 'datetime',
            'ad_last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'deactivated_by');
    }

    public function readerProfile(): HasOne
    {
        return $this->hasOne(ReaderProfile::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function libraryReservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function libraryFines(): HasMany
    {
        return $this->hasMany(Fine::class);
    }

    public function assignedLibraryTasks(): HasMany
    {
        return $this->hasMany(LibraryTask::class, 'assigned_to');
    }

    public function readerNotifications(): HasMany
    {
        return $this->hasMany(ReaderNotification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_id');
    }

    /**
     * Resolve the role that owns the user's primary workspace. A retained
     * reader/member role must never override an explicitly assigned staff role.
     */
    public function effectiveRole(): string
    {
        $roles = $this->getRoleNames()
            ->map(fn (string $role): string => mb_strtolower(trim($role)))
            ->filter()
            ->values();

        foreach (self::WORKSPACE_ROLE_PRECEDENCE as $role) {
            if ($roles->contains($role)) {
                return $role;
            }
        }

        if ($roles->isNotEmpty()) {
            return (string) $roles->first();
        }

        return match (mb_strtolower(trim((string) $this->role))) {
            'admin' => 'admin',
            'librarian' => 'librarian',
            default => 'member',
        };
    }

    public function assignedContactMessages(): HasMany
    {
        return $this->hasMany(ContactMessage::class, 'assigned_to');
    }
}
