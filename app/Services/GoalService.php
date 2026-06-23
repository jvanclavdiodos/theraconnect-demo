<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinician;
use App\Models\GoalRating;
use App\Models\Patient;
use App\Models\TherapyGoal;

class GoalService
{
    /** Co-define a therapy goal for a patient (status: active). */
    public function create(Patient $patient, Clinician $clinician, array $data): TherapyGoal
    {
        return TherapyGoal::create([
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
            'description' => $data['description'],
            'target_date' => $data['target_date'] ?? null,
            'status' => 'active',
        ]);
    }

    /** Record a Goal Attainment Scaling rating (-2…+2) for a review. */
    public function rate(TherapyGoal $goal, int $rating, ?string $note = null, ?Appointment $appointment = null): GoalRating
    {
        return $goal->ratings()->create([
            'rating' => $rating,
            'note' => $note,
            'appointment_id' => $appointment?->id,
        ]);
    }

    /** Move a goal between active / met / dropped. */
    public function setStatus(TherapyGoal $goal, string $status): TherapyGoal
    {
        $goal->update(['status' => $status]);

        return $goal->fresh();
    }
}
