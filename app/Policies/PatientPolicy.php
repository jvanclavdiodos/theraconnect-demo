<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    /**
     * Who may view a patient record on the web dashboard. Admins see every
     * patient; a clinician sees only patients assigned to them. Patients never
     * reach this (the patient surface is the API, not the dashboard).
     */
    public function view(User $user, Patient $patient): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'clinician'
            && $user->clinician !== null
            && $patient->assigned_clinician_id === $user->clinician->id;
    }
}
