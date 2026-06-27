<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required'],
        ]);

        // Case-insensitive login (matches the User::setEmailAttribute mutator
        // that lowercases emails on save). Replaces Auth::attempt with a manual
        // lookup + Hash::check so we can also run a dummy Hash::check when the
        // user doesn't exist — see the inline comment below.
        $credentials['email'] = strtolower($credentials['email']);

        $user = User::where('email', $credentials['email'])->first();

        // Anti-enumeration: always Hash::check against a real hash. When the
        // user doesn't exist, we compare against a freshly-minted dummy so the
        // failure path takes the same approximate time as a wrong-password
        // attempt against a valid account — mitigating timing-based email
        // enumeration. Mutator + lowercase lookup here form the full casing fix.
        $storedHash = $user?->password ?? Hash::make(str()->random(32));

        if (! $user || ! Hash::check($credentials['password'], $storedHash)) {
            return back()
                ->withErrors(['email' => 'The provided credentials do not match our records.'])
                ->onlyInput('email');
        }

        Auth::login($user);
        $request->session()->regenerate();

        // Patients use the browser portal; clinicians/admins use the dashboard.
        if (Auth::user()->role === 'patient') {
            return redirect()->intended(route('portal.dashboard'));
        }

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
