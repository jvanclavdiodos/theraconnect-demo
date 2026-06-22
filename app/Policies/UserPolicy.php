<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Staff may serve their own avatar, admins may serve any, and clinicians
     * may only serve avatars for patients on their caseload. Patients never
     * hit this endpoint (it lives in the role:admin,clinician web group).
     */
    public function viewAvatar(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return true;
        }

        if ($actor->role === 'admin') {
            return true;
        }

        if ($actor->role === 'clinician' && $actor->clinician && $target->patient) {
            return $target->patient->assigned_clinician_id === $actor->clinician->id;
        }

        return false;
    }
}
