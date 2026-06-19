<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function create(User $user): bool
    {
        return $user->role === 'patient'
            && $user->patient !== null;
    }

    /**
     * A patient may view their own submission. Admins/clinicians may view
     * any submission (they review them via the web dashboard).
     *
     * @return bool
     */
    public function view(User $user, Submission $submission): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        // A clinician may only view submissions for assignments they authored.
        if ($user->role === 'clinician') {
            return $user->clinician !== null
                && $submission->assignment
                && $submission->assignment->clinician_id === $user->clinician->id;
        }

        return $user->patient !== null && $submission->patient_id === $user->patient->id;
    }

    /**
     * Mark a submission reviewed (web dashboard). Admin any; clinician only
     * submissions belonging to their own assignments.
     */
    public function review(User $user, Submission $submission): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'clinician'
            && $user->clinician !== null
            && $submission->assignment
            && $submission->assignment->clinician_id === $user->clinician->id;
    }

    /**
     * Patients may not delete submission rows after review (would lose the
     * clinician's review trail). Admins/clinicians may delete via the web
     * dashboard. Used for completeness — there is no /api/v1/submissions
     * DELETE route today, but the policy locks down the operation if one
     * is added.
     *
     * @return bool
     */
    public function delete(User $user, Submission $submission): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        // A clinician may delete only submissions for their own assignments.
        if ($user->role === 'clinician') {
            return $user->clinician !== null
                && $submission->assignment
                && $submission->assignment->clinician_id === $user->clinician->id;
        }

        // Patients may delete only their own submissions and only before
        // they have been reviewed (matches the controller's 409 short-circuit
        // for re-submission after review).
        return $user->patient !== null
            && $submission->patient_id === $user->patient->id
            && $submission->status !== 'reviewed';
    }
}
