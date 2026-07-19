<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Patient;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const UPCOMING_STATUSES = ['pending', 'approved', 'rescheduled'];

    public function index(): View
    {
        $user = auth()->user();

        // Role-aware: an admin sees clinic-wide totals; a clinician sees only
        // their own caseload (their patients / appointments / assignments).
        $clinicianId = ($user->role === 'clinician' && $user->clinician)
            ? $user->clinician->id
            : null;

        $totalPatients = $clinicianId
            ? Patient::assignedTo($clinicianId)->count()
            : Patient::count();

        $appointments = fn () => $clinicianId
            ? Appointment::where('clinician_id', $clinicianId)
            : Appointment::query();

        $pendingAppointments = $appointments()->where('status', 'pending')->count();
        $todayAppointments = $appointments()->whereDate('scheduled_at', today())->count();
        $upcomingAppointments = $appointments()
            ->whereNotNull('patient_id')
            ->whereHas('patient')
            ->whereIn('status', self::UPCOMING_STATUSES)
            ->where(function ($query) {
                $query->where('scheduled_at', '>=', now())
                    ->orWhere(function ($pending) {
                        $pending->whereNull('scheduled_at')
                            ->where('requested_at', '>=', now());
                    });
            })
            ->with('patient.user')
            ->orderByRaw('COALESCE(scheduled_at, requested_at) ASC')
            ->take(5)
            ->get();

        $assignments = fn () => $clinicianId
            ? Assignment::where('clinician_id', $clinicianId)
            : Assignment::query();

        $pendingAssignments = $assignments()
            ->with('patient.user')
            ->whereHas('submissions', fn ($q) => $q->where('status', '!=', 'reviewed'))
            ->latest()
            ->take(5)
            ->get();

        return view('clinician.dashboard', compact(
            'totalPatients',
            'pendingAppointments',
            'todayAppointments',
            'upcomingAppointments',
            'pendingAssignments',
        ));
    }
}
