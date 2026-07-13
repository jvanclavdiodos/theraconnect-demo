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
            && $patient->isAssignedTo($user->clinician);
    }

    /**
     * Who may approve/deny a patient's pending clinician request. Admins may
     * act on any request; a clinician may only act on requests addressed to
     * them. The request must still be pending (an already-answered request is
     * not re-decidable).
     */
    public function respondToRequest(User $user, Patient $patient): bool
    {
        if ($patient->clinician_request_status !== Patient::REQUEST_PENDING) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'clinician'
            && $user->clinician !== null
            && $patient->requested_clinician_id === $user->clinician->id;
    }
}
