<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Patient;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalPatients = Patient::count();
        $pendingAppointments = Appointment::where('status', 'pending')->count();
        $todayAppointments = Appointment::whereDate('scheduled_at', today())->count();

        $recentAppointments = Appointment::with('patient.user')
            ->latest()
            ->take(5)
            ->get();

        $pendingAssignments = Assignment::with('patient.user')
            ->withCount('submissions')
            ->whereHas('submissions')
            ->whereDoesntHave('submissions', fn ($q) => $q->where('status', 'reviewed'))
            ->latest()
            ->take(5)
            ->get();

        return view('clinician.dashboard', compact(
            'totalPatients',
            'pendingAppointments',
            'todayAppointments',
            'recentAppointments',
            'pendingAssignments',
        ));
    }
}
