<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PasswordController extends Controller
{
    /**
     * Patient self-service password change (mobile). The new password is set on
     * the User model whose `password` cast hashes it on save. The token used for
     * this request is kept; all OTHER tokens are revoked so a stolen/forgotten
     * session elsewhere can't outlive a password change. Both writes share a
     * transaction — if token revocation fails, the password change rolls back
     * (a stolen token otherwise outlives the user's password change).
     */
    public function update(ChangePasswordRequest $request): Response
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $request) {
            $user->password = $request->validated('password');
            $user->save();

            $currentTokenId = $user->currentAccessToken()->id;
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        });

        return response()->noContent();
    }
}
