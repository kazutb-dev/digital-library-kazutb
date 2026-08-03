<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    'external_id',
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
            'deactivated_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'deactivated_by');
    }

    public function readerProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Catalog\ReaderProfile::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(\App\Models\Catalog\Loan::class);
    }

    public function libraryReservations(): HasMany
    {
        return $this->hasMany(\App\Models\Catalog\Reservation::class);
    }

    public function libraryFines(): HasMany
    {
        return $this->hasMany(\App\Models\Catalog\Fine::class);
    }

    public function readerNotifications(): HasMany
    {
        return $this->hasMany(\App\Models\Catalog\ReaderNotification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_id');
    }
}
