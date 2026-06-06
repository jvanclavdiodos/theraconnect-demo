<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebAppointmentController extends Controller
{
    public function __construct(
        private AppointmentService $appointmentService,
        private NotificationService $notificationService,
    ) {}

    public function index(Request $request): View
    {
        $query = Appointment::with(['patient.user', 'clinician.user'])
            ->latest('requested_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->paginate(20);

        return view('appointments.index', compact('appointments'));
    }

    public function approve(Appointment $appointment): RedirectResponse
    {
        $appointment = $this->appointmentService->approve($appointment);

        $notification = $this->notificationService->appointmentApproved(
            $appointment->patient->user->id,
            $appointment->scheduled_at->format('M d, Y h:i A')
        );

        SendPushNotification::dispatch($notification->id)->afterCommit();

        return redirect()->route('appointments.index')
            ->with('status', 'Appointment approved.');
    }

    public function reject(Appointment $appointment): RedirectResponse
    {
        $this->appointmentService->reject($appointment);

        $notification = $this->notificationService->appointmentRejected(
            $appointment->patient->user->id
        );

        SendPushNotification::dispatch($notification->id)->afterCommit();

        return redirect()->route('appointments.index')
            ->with('status', 'Appointment rejected.');
    }

    public function reschedule(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $appointment = $this->appointmentService->reschedule($appointment, $validated['scheduled_at']);

        $notification = $this->notificationService->appointmentRescheduled(
            $appointment->patient->user->id,
            $appointment->scheduled_at->format('M d, Y h:i A')
        );

        SendPushNotification::dispatch($notification->id)->afterCommit();

        return redirect()->route('appointments.index')
            ->with('status', 'Appointment rescheduled.');
    }
}
