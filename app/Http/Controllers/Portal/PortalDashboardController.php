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

        $upcomingQuery = Appointment::where('patient_id', $patient->id)
            ->upcoming();

        $upcomingAppointmentsCount = (clone $upcomingQuery)->count();

        $upcoming = $upcomingQuery
            ->with('clinician.user')
            ->orderByRaw('COALESCE(scheduled_at, requested_at) ASC')
            ->orderBy('id')
            ->take(5)
            ->get();

        $pendingAssignments = Assignment::where('patient_id', $patient->id)
            ->whereDoesntHave('submissions', fn ($q) => $q->where('patient_id', $patient->id))
            ->count();

        $pendingAssessments = Assessment::where('patient_id', $patient->id)
            ->where('status', 'pending')
            ->get();

        $unreadNotifications = Notification::where('user_id', $request->user()->id)
            ->unreadGeneral()
            ->count();

        return view('portal.dashboard', compact(
            'patient',
            'upcoming',
            'upcomingAppointmentsCount',
            'pendingAssignments',
            'pendingAssessments',
            'unreadNotifications'
        ));
    }
}
