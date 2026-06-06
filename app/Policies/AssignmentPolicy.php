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
}
