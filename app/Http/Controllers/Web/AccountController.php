<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateAvatarRequest;
use App\Models\User;
use App\Rules\StrongPassword;
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

    /** Change the current staff user's password (current-password verified). */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', new StrongPassword],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different' => 'The new password must be different from your current password.',
        ]);

        $user = $request->user();
        $user->password = $request->password;
        $user->save();

        return back()->with('status', 'Password updated.');
    }

    public function updateAvatar(UpdateAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Store new first, then update DB, then delete old — file ops can't
        // roll back, so this order ensures a store failure leaves the user's
        // existing avatar intact rather than deleting it before the new
        // upload lands.
        $oldPath = $user->avatar_path;
        $path = $request->file('avatar')->store('avatars');
        $user->update(['avatar_path' => $path]);

        if ($oldPath) {
            Storage::disk()->delete($oldPath);
        }

        return back()->with('status', 'Profile picture updated.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk()->delete($user->avatar_path);
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
            $user->avatar_path && Storage::disk()->exists($user->avatar_path),
            404
        );

        return Storage::disk()->response($user->avatar_path);
    }
}
