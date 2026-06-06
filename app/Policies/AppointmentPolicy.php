<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        if (in_array($user->role, ['admin', 'clinician'])) {
            return true;
        }

        return $user->patient && $appointment->patient_id === $user->patient->id;
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->view($user, $appointment);
    }
}
