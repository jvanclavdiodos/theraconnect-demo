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

    /**
     * Staff ability for the web dashboard's appointment actions (approve /
     * reject / reschedule). An admin manages every appointment; a clinician
     * may only act on appointments assigned to them. This is the boundary that
     * stops one clinician from approving/rescheduling another's appointments.
     */
    public function manage(User $user, Appointment $appointment): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'clinician'
            && $user->clinician !== null
            && $appointment->clinician_id === $user->clinician->id;
    }

    /**
     * Patients may cancel their own appointments. Cancellable states are
     * pending, approved, rescheduled, and cancelled. `cancelled` is permitted
     * here so the controller can return a friendly 409 ("already cancelled")
     * instead of an opaque 403 from Gate::authorize().
     *
     * `completed` and `rejected` are TERMINAL states: cancelling a completed
     * appointment flips a finalized record (desyncs clinician reporting) and
     * cancelling a rejected one is a no-op that nonetheless mutates the row.
     * Both are refused with 403 for patients.
     *
     * Admins/clinicians bypass this check — they may cancel any appointment
     * via the web dashboard.
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        // Ownership / role check first.
        if (! $this->view($user, $appointment)) {
            return false;
        }

        // Web dashboard users (admin / clinician) may cancel any appointment
        // in any state — they manage the schedule end-to-end.
        if (in_array($user->role, ['admin', 'clinician'])) {
            return true;
        }

        // Patients may not cancel appointments in terminal states.
        return ! in_array($appointment->status, ['completed', 'rejected']);
    }
}
