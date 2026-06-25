<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use Illuminate\Http\Response;

class PasswordController extends Controller
{
    /**
     * Patient self-service password change (mobile). The new password is set on
     * the User model whose `password` cast hashes it on save. The token used for
     * this request is kept; all OTHER tokens are revoked so a stolen/forgotten
     * session elsewhere can't outlive a password change.
     */
    public function update(ChangePasswordRequest $request): Response
    {
        $user = $request->user();
        $user->password = $request->validated('password');
        $user->save();

        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->noContent();
    }
}
