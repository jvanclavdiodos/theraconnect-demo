<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarResponse
{
    public static function make(Request $request, User $user): StreamedResponse
    {
        $response = Storage::disk()->response($user->avatar_path);
        $response->setPrivate();
        // Keep image bytes locally, but recheck permission before reusing them.
        $response->headers->addCacheControlDirective('no-cache');
        $response->setVary(['Cookie', 'Authorization']);
        $response->setEtag(hash('sha256', implode('|', [
            $request->user()->id,
            $user->id,
            $user->avatar_path,
            $user->updated_at?->format('U.u'),
        ])));
        $response->isNotModified($request);

        return $response;
    }
}
