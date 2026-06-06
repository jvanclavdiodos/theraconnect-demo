<?php

namespace App\Policies;

use App\Models\User;

class SubmissionPolicy
{
    public function create(User $user): bool
    {
        return $user->role === 'patient'
            && $user->patient !== null;
    }
}
