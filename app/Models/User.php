<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Normalize emails to lowercase on save so login is case-insensitive
     * regardless of the underlying DB collation (`utf8mb4_unicode_ci` on MySQL
     * production is case-insensitive today, but SQLite test DBs and any `_bin`
     * collation change would silently break lookups otherwise). Login lookups
     * also lowercase the input — see Api\V1\AuthController::login and
     * Web\AuthenticatedSessionController::store.
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    public function clinician(): HasOne
    {
        return $this->hasOne(Clinician::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function hasAvatar(): bool
    {
        return ! empty($this->avatar_path);
    }
}
