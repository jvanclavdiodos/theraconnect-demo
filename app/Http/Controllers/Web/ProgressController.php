<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
    ) {}

    /** Therapy-progress dashboard for one patient (attendance, scales, mood, goals). */
    public function show(Patient $patient): View
    {
        Gate::authorize('view', $patient);

        $patient->load('user', 'assignedClinician.user');

        $attendance = $this->attendance->statsFor($patient);

        // Recent terminal sessions (attended / missed / cancelled), newest first,
        // so the clinician can read the engagement pattern at a glance.
        $sessions = Appointment::where('patient_id', $patient->id)
            ->whereIn('status', ['completed', 'no_show', 'cancelled'])
            ->whereNotNull('scheduled_at')
            ->latest('scheduled_at')
            ->take(12)
            ->get(['id', 'status', 'scheduled_at', 'mode']);

        return view('patients.progress', compact('patient', 'attendance', 'sessions'));
    }
}
