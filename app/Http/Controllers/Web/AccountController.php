<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    /** The current staff user's account page (profile picture). */
    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'local');
        $user->update(['avatar_path' => $path]);

        return back()->with('status', 'Profile picture updated.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return back()->with('status', 'Profile picture removed.');
    }

    /**
     * Serve any user's avatar inline from the private disk. Staff-gated by the
     * route group; used for the current user's chip and patients' avatars.
     */
    public function showAvatar(User $user): StreamedResponse
    {
        Gate::authorize('viewAvatar', $user);

        abort_unless(
            $user->avatar_path && Storage::disk('local')->exists($user->avatar_path),
            404
        );

        return Storage::disk('local')->response($user->avatar_path);
    }
}
