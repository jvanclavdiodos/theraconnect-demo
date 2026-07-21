<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Only the two participants (the patient's user and the clinician's user)
     * may view a conversation or post to it. This is the boundary that keeps
     * one patient/clinician out of another pair's thread.
     */
    public function participate(User $user, Conversation $conversation): bool
    {
        $conversation->loadMissing(['patient', 'clinician']);

        // Thread creation and inbox discovery are assignment-gated. Once a
        // thread exists, both participants retain access to its history.
        return $conversation->hasParticipant($user);
    }

    /** Only current patient-clinician assignments may exchange new messages. */
    public function send(User $user, Conversation $conversation): bool
    {
        $conversation->loadMissing(['patient.assignedClinicians', 'clinician']);

        return $conversation->hasParticipant($user)
            && $conversation->patient !== null
            && $conversation->clinician !== null
            && $conversation->patient->isAssignedTo($conversation->clinician);
    }
}
