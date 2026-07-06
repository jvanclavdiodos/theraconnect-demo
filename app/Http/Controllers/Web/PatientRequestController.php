<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Services\PatientRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Clinician/admin handling of a self-registered patient's request to join a
 * clinician's caseload. Approving sets assigned_clinician_id (the boundary the
 * patients tab + messaging scope on); denying leaves the patient unassigned.
 */
class PatientRequestController extends Controller
{
    public function __construct(
        private PatientRequestService $patientRequests,
        private NotificationService $notifications,
    ) {}

    public function approve(Request $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('respondToRequest', $patient);

        $notification = DB::transaction(function () use ($patient) {
            return $this->patientRequests->approve($patient);
        });

        $this->notifications->dispatchDeliveries($notification);

        app(ActivityLogService::class)->log($request->user(), 'patient.request_approved', $patient);

        return redirect()->route('patients.index')
            ->with('status', $patient->user->name.' was added to the caseload.');
    }

    public function deny(Request $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('respondToRequest', $patient);

        $notification = DB::transaction(function () use ($patient) {
            return $this->patientRequests->deny($patient);
        });

        $this->notifications->dispatchDeliveries($notification);

        app(ActivityLogService::class)->log($request->user(), 'patient.request_denied', $patient);

        return redirect()->route('patients.index')
            ->with('status', 'Clinician request declined.');
    }
}
