<?php

namespace App\Services;

use App\Models\Submission;

class AssignmentBadgeService
{
    public function clinicianPendingReviewCount(int $clinicianId): int
    {
        return Submission::query()
            ->where('status', 'submitted')
            ->whereHas('assignment', fn ($query) => $query->where('clinician_id', $clinicianId))
            ->count();
    }

    public function patientAwaitingReviewCount(int $patientId): int
    {
        return Submission::query()
            ->where('patient_id', $patientId)
            ->where('status', 'submitted')
            ->count();
    }
}
