<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    public const GENDERS = ['Male', 'Female', 'Other', 'Prefer not to say'];

    public const EDUCATION_LEVELS = ['None', 'Elementary', 'High School', 'Vocational', 'College', 'Postgraduate'];

    public const EMPLOYMENT_STATUSES = ['Employed', 'Self-employed', 'Unemployed', 'Student', 'Retired'];

    // Lifecycle of a patient's request to be assigned a clinician at sign-up.
    public const REQUEST_PENDING = 'pending';

    public const REQUEST_APPROVED = 'approved';

    public const REQUEST_DENIED = 'denied';

    protected $fillable = [
        'user_id',
        'assigned_clinician_id',
        'requested_clinician_id',
        'clinician_request_status',
        'date_of_birth',
        'gender',
        'educational_attainment',
        'employment_status',
        'personal_issues',
        'contact_no',
        'address',
        'emergency_contact',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'personal_issues' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedClinician(): BelongsTo
    {
        return $this->belongsTo(Clinician::class, 'assigned_clinician_id');
    }

    public function assignedClinicians(): BelongsToMany
    {
        return $this->belongsToMany(Clinician::class)->withTimestamps();
    }

    public function assignClinician(Clinician|int $clinician): void
    {
        $clinicianId = $clinician instanceof Clinician ? $clinician->id : $clinician;

        $this->assignedClinicians()->syncWithoutDetaching([$clinicianId]);

        // Compatibility for older clients and code during the pivot rollout.
        if ($this->assigned_clinician_id === null) {
            $this->update(['assigned_clinician_id' => $clinicianId]);
        }
    }

    public function isAssignedTo(Clinician|int $clinician): bool
    {
        $clinicianId = $clinician instanceof Clinician ? $clinician->id : $clinician;

        if ($this->relationLoaded('assignedClinicians')
            && $this->assignedClinicians->contains('id', $clinicianId)) {
            return true;
        }

        return $this->assigned_clinician_id === $clinicianId
            || $this->assignedClinicians()->whereKey($clinicianId)->exists();
    }

    public function scopeAssignedTo(Builder $query, Clinician|int $clinician): Builder
    {
        $clinicianId = $clinician instanceof Clinician ? $clinician->id : $clinician;

        return $query->where(function (Builder $query) use ($clinicianId) {
            $query->where('assigned_clinician_id', $clinicianId)
                ->orWhereHas('assignedClinicians', fn (Builder $q) => $q->whereKey($clinicianId));
        });
    }

    public function requestedClinician(): BelongsTo
    {
        return $this->belongsTo(Clinician::class, 'requested_clinician_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function clinicianNotes(): HasMany
    {
        return $this->hasMany(PatientNote::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function moodLogs(): HasMany
    {
        return $this->hasMany(MoodLog::class);
    }

    public function therapyGoals(): HasMany
    {
        return $this->hasMany(TherapyGoal::class);
    }
}
