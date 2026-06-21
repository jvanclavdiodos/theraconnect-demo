<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Clinician extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'license_no',
        'specialization',
        'contact_no',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function weeklyAvailabilities(): HasMany
    {
        return $this->hasMany(ClinicianWeeklyAvailability::class);
    }

    public function dateOverrides(): HasMany
    {
        return $this->hasMany(ClinicianDateOverride::class);
    }
}
