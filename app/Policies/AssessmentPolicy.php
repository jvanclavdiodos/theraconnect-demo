<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

class AssessmentPolicy
{
    /**
     * A patient may only view/complete their own assessments. Staff (admin /
     * clinician) read assessments through the web dashboard, not this API.
     */
    public function complete(User $user, Assessment $assessment): bool
    {
        return $user->patient !== null
            && $assessment->patient_id === $user->patient->id;
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $this->complete($user, $assessment);
    }
}
