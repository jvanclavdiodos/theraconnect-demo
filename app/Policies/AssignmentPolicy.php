<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    public function view(User $user, Assignment $assignment): bool
    {
        if (in_array($user->role, ['admin', 'clinician'])) {
            return true;
        }

        return $user->patient && $assignment->patient_id === $user->patient->id;
    }

    /**
     * Staff ability for web dashboard assignment actions (view submissions,
     * download worksheet). Admin manages all; a clinician only their own
     * assignments — stops one clinician from reading another's assignment
     * submissions.
     */
    public function manage(User $user, Assignment $assignment): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'clinician'
            && $user->clinician !== null
            && $assignment->clinician_id === $user->clinician->id;
    }
}
