<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateAvatarRequest;
use App\Models\Patient;
use App\Rules\StrongPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalProfileController extends Controller
{
    public function show(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);
        $patient->load('user', 'assignedClinician.user');

        return view('portal.profile.show', compact('patient'));
    }

    public function edit(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);
        $patient->load('user');

        return view('portal.profile.edit', compact('patient'));
    }

    public function update(Request $request): RedirectResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $validated = $request->validate([
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', Rule::in(Patient::GENDERS)],
            'educational_attainment' => ['nullable', 'string', Rule::in(Patient::EDUCATION_LEVELS)],
            'employment_status' => ['nullable', 'string', Rule::in(Patient::EMPLOYMENT_STATUSES)],
            'personal_issues' => ['nullable', 'string', 'max:2000'],
            'contact_no' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
        ]);

        $patient->update($validated);

        return redirect()
            ->route('portal.profile.show')
            ->with('status', 'Profile updated.');
    }

    /** Change the patient's own password (current-password verified). */
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

        // Mirror Api\V1\PasswordController::update: wrap the password change
        // + Sanctum token revocation in a single transaction so a revocation
        // failure rolls back the password change. The portal uses session
        // auth, so we revoke ALL bearer tokens (the patient's other mobile
        // devices + any attacker-stolen tokens) — the patient re-auths their
        // mobile app on next launch. Also invalidates other browser sessions
        // to close hijacked-session windows. Without this, a patient who
        // suspects compromise and changes their password leaves stolen bearer
        // tokens valid for up to 7 days (Sanctum expiration).
        DB::transaction(function () use ($user, $request) {
            $user->password = $request->password;
            $user->save();

            $user->tokens()->delete();

            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', session()->getId())
                ->delete();
        });

        return redirect()
            ->route('portal.profile.show')
            ->with('status', 'Password updated.');
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

    /** Serve the patient's own avatar inline from the private disk. */
    public function avatar(Request $request): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $user->avatar_path && Storage::disk()->exists($user->avatar_path),
            404
        );

        return Storage::disk()->response($user->avatar_path);
    }
}
