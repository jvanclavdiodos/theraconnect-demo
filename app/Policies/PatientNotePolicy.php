<?php

namespace App\Policies;

use App\Models\PatientNote;
use App\Models\User;

class PatientNotePolicy
{
    /**
     * A clinician may edit/delete only the notes they authored. (Creating a
     * note is gated separately by a caseload check in the controller, since it
     * needs the target patient.)
     */
    public function manage(User $user, PatientNote $note): bool
    {
        return $user->role === 'clinician'
            && $user->clinician !== null
            && $note->clinician_id === $user->clinician->id;
    }
}
