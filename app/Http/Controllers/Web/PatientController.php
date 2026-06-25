<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinician;
use App\Models\Patient;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PatientController extends Controller
{

    public function index(Request $request): View
    {
        $query = Patient::with('user')->latest();

        // Clinicians see only patients assigned to them; admins see all.
        $user = $request->user();
        if ($user->role === 'clinician' && $user->clinician) {
            $query->where('assigned_clinician_id', $user->clinician->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                  ->orWhere('contact_no', 'like', "%{$search}%");
            });
        }

        $patients = $query->paginate(20)->appends($request->query());

        // Flag patients with a current no-show streak so disengagement is
        // visible on the list without opening each profile.
        $atRisk = app(\App\Services\AttendanceService::class)
            ->atRiskPatientIds($patients->getCollection());

        return view('patients.index', compact('patients', 'atRisk'));
    }

    public function create(): View
    {
        $clinicians = Clinician::with('user')->orderBy('id')->get();

        return view('patients.create', compact('clinicians'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', new StrongPassword],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', Rule::in(Patient::GENDERS)],
            'educational_attainment' => ['nullable', 'string', Rule::in(Patient::EDUCATION_LEVELS)],
            'employment_status' => ['nullable', 'string', Rule::in(Patient::EMPLOYMENT_STATUSES)],
            'personal_issues' => ['nullable', 'string', 'max:2000'],
            'contact_no' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'assigned_clinician_id' => ['nullable', 'exists:clinicians,id'],
        ]);

        // A clinician always onboards patients onto their OWN caseload — their
        // clinician id is forced regardless of any submitted value, so they
        // can't assign a patient to someone else. An admin chooses freely (the
        // form's clinician dropdown), or leaves it unassigned.
        $actor = $request->user();
        $assignedClinicianId = ($actor->role === 'clinician' && $actor->clinician)
            ? $actor->clinician->id
            : ($validated['assigned_clinician_id'] ?? null);

        DB::transaction(function () use ($validated, $assignedClinicianId) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'patient',
            ]);

            Patient::create([
                'user_id' => $user->id,
                'assigned_clinician_id' => $assignedClinicianId,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'educational_attainment' => $validated['educational_attainment'] ?? null,
                'employment_status' => $validated['employment_status'] ?? null,
                'personal_issues' => $validated['personal_issues'] ?? null,
                'contact_no' => $validated['contact_no'] ?? null,
                'address' => $validated['address'] ?? null,
                'emergency_contact' => $validated['emergency_contact'] ?? null,
            ]);
        });

        return redirect()->route('patients.index')
            ->with('status', 'Patient created successfully.');
    }

    public function show(Request $request, Patient $patient): View
    {
        Gate::authorize('view', $patient);

        app(ActivityLogService::class)->log($request->user(), 'patient.viewed', $patient);

        $patient->load([
            'user',
            'assignedClinician.user',
            'clinicianNotes' => fn ($q) => $q->with('clinician.user')->latest(),
        ]);
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
        $clinicians = Clinician::with('user')->orderBy('id')->get();

        return view('patients.edit', compact('patient', 'clinicians'));
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $patient->user_id],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', Rule::in(Patient::GENDERS)],
            'educational_attainment' => ['nullable', 'string', Rule::in(Patient::EDUCATION_LEVELS)],
            'employment_status' => ['nullable', 'string', Rule::in(Patient::EMPLOYMENT_STATUSES)],
            'personal_issues' => ['nullable', 'string', 'max:2000'],
            'contact_no' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'assigned_clinician_id' => ['nullable', 'exists:clinicians,id'],
        ]);

        $patient->user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $patient->update([
            'assigned_clinician_id' => $validated['assigned_clinician_id'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'educational_attainment' => $validated['educational_attainment'] ?? null,
            'employment_status' => $validated['employment_status'] ?? null,
            'personal_issues' => $validated['personal_issues'] ?? null,
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
