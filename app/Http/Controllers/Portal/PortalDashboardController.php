<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404, 'Patient profile not found.');

        $upcoming = Appointment::where('patient_id', $patient->id)
            ->whereIn('status', ['pending', 'approved', 'rescheduled'])
            ->with('clinician.user')
            ->orderBy('requested_at')
            ->take(5)
            ->get();

        $pendingAssignments = Assignment::where('patient_id', $patient->id)
            ->whereDoesntHave('submissions', fn ($q) => $q->where('patient_id', $patient->id))
            ->count();

        $pendingAssessments = Assessment::where('patient_id', $patient->id)
            ->where('status', 'pending')
            ->get();

        $unreadNotifications = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return view('portal.dashboard', compact(
            'patient', 'upcoming', 'pendingAssignments', 'pendingAssessments', 'unreadNotifications'
        ));
    }
}
