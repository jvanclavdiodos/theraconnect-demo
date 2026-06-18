<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PatientController extends Controller
{

    public function index(Request $request): View
    {
        $query = Patient::with('user')->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                  ->orWhere('contact_no', 'like', "%{$search}%");
            });
        }

        $patients = $query->paginate(20)->appends($request->query());

        return view('patients.index', compact('patients'));
    }

    public function create(): View
    {
        return view('patients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'date_of_birth' => ['nullable', 'date'],
            'contact_no' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'patient',
            ]);

            Patient::create([
                'user_id' => $user->id,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'contact_no' => $validated['contact_no'] ?? null,
                'address' => $validated['address'] ?? null,
                'emergency_contact' => $validated['emergency_contact'] ?? null,
            ]);
        });

        return redirect()->route('patients.index')
            ->with('status', 'Patient created successfully.');
    }

    public function show(Patient $patient): View
    {
        $patient->load('user');
        $appointments = Appointment::where('patient_id', $patient->id)
            ->with('clinician.user')
            ->latest('requested_at')
            ->take(10)
            ->get();

        return view('patients.show', compact('patient', 'appointments'));
    }

    public function edit(Patient $patient): View
    {
        $patient->load('user');

        return view('patients.edit', compact('patient'));
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $patient->user_id],
            'date_of_birth' => ['nullable', 'date'],
            'contact_no' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $patient->user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $patient->update([
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'contact_no' => $validated['contact_no'] ?? null,
            'address' => $validated['address'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('patients.index')
            ->with('status', 'Patient updated successfully.');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        // Soft-delete the related User too — otherwise the User row stays
        // active: the email remains "taken" (blocks re-registration), the
        // orphaned user can still authenticate (then 404s on getPatient()),
        // and a fresh audit trail is broken. Both rows are soft-deletable
        // (User & Patient models use SoftDeletes) so historical data is
        // preserved; restore brings back both.
        DB::transaction(function () use ($patient) {
            $patient->delete();

            if ($patient->user) {
                $patient->user->delete();
            }
        });

        return redirect()->route('patients.index')
            ->with('status', 'Patient deleted successfully.');
    }
}
