<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Patient;
use App\Services\AssessmentService;
use App\Services\AttendanceService;
use App\Services\NotificationService;
use App\Support\Assessments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private AssessmentService $assessments,
        private NotificationService $notifications,
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

        // Completed assessments → score trend per instrument (oldest → newest).
        $completed = Assessment::where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->orderBy('completed_at')
            ->get();
        $scoreTrends = $completed->groupBy('instrument');

        $pendingAssessments = Assessment::where('patient_id', $patient->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        $instruments = Assessments::INSTRUMENTS;

        return view('patients.progress', compact(
            'patient', 'attendance', 'sessions', 'scoreTrends', 'pendingAssessments', 'instruments'
        ));
    }

    /** Assign a PHQ-9 / GAD-7 to the patient and notify them in-app. */
    public function assignAssessment(Request $request, Patient $patient): RedirectResponse
    {
        $clinician = $request->user()->clinician;
        abort_unless($clinician !== null, 403, 'No clinician profile.');

        // A clinician may only assign to a patient on their caseload.
        abort_unless($patient->assigned_clinician_id === $clinician->id, 403);

        $validated = $request->validate([
            'instrument' => ['required', 'in:' . implode(',', array_keys(Assessments::INSTRUMENTS))],
        ]);

        $notification = DB::transaction(function () use ($patient, $clinician, $validated) {
            $assessment = $this->assessments->assign($patient, $clinician, $validated['instrument']);

            return $this->notifications->assessmentAssigned(
                $patient->user->id,
                $assessment->title()
            );
        });

        SendPushNotification::dispatch($notification->id)->afterCommit();

        return redirect()->route('patients.progress', $patient)
            ->with('status', Assessments::title($validated['instrument']) . ' assigned to the patient.');
    }
}
