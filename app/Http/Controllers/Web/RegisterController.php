<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\TermsOfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Patient self-registration for the browser portal. Staff accounts are
 * admin-provisioned, never self-registered.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Critical fields: required.
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
            'accepted_terms' => ['required', 'accepted'],
            // Optional patient profile fields captured at sign-up.
            'contact_no' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'string', Rule::in(Patient::GENDERS)],
            'educational_attainment' => ['nullable', 'string', Rule::in(Patient::EDUCATION_LEVELS)],
            'employment_status' => ['nullable', 'string', Rule::in(Patient::EMPLOYMENT_STATUSES)],
            'personal_issues' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'patient',
                'terms_accepted_at' => now(),
                'terms_version' => TermsOfService::CURRENT_VERSION,
            ]);

            Patient::create([
                'user_id' => $user->id,
                'contact_no' => $validated['contact_no'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'educational_attainment' => $validated['educational_attainment'] ?? null,
                'employment_status' => $validated['employment_status'] ?? null,
                'personal_issues' => $validated['personal_issues'] ?? null,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }
}
