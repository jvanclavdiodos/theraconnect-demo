<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        // Clinicians see only their own caseload; admins see every appointment.
        $user = $request->user();
        if ($user->role === 'clinician' && $user->clinician) {
            $query->where('clinician_id', $user->clinician->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->paginate(20);

        return view('appointments.index', compact('appointments'));
    }

    public function approve(Appointment $appointment): RedirectResponse
    {
        Gate::authorize('manage', $appointment);

        $notification = DB::transaction(function () use ($appointment) {
            $this->appointmentService->approve($appointment);

            return $this->notificationService->appointmentApproved(
                $appointment->patient->user->id,
                $appointment->scheduled_at->format('M d, Y h:i A'),
                $appointment->meeting_link
            );
        });

        SendPushNotification::dispatch($notification->id)->afterCommit();

        return redirect()->route('appointments.index')
            ->with('status', 'Appointment approved.');
    }

    public function reject(Appointment $appointment): RedirectResponse
    {
        Gate::authorize('manage', $appointment);

        $notification = DB::transaction(function () use ($appointment) {
            $this->appointmentService->reject($appointment);

            return $this->notificationService->appointmentRejected(
                $appointment->patient->user->id
            );
        });

        SendPushNotification::dispatch($notification->id)->afterCommit();

        return redirect()->route('appointments.index')
            ->with('status', 'Appointment rejected.');
    }

    public function reschedule(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('manage', $appointment);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        try {
            $notifications = DB::transaction(function () use ($appointment, $validated, $request) {
                $appointment = $this->appointmentService->reschedule($appointment, $validated['scheduled_at']);
                $appointment->load('patient.user', 'clinician.user');

                $scheduledAt = $appointment->scheduled_at->format('M d, Y h:i A');

                $created = [
                    // The patient learns their appointment moved.
                    $this->notificationService->appointmentRescheduled(
                        $appointment->patient->user->id,
                        $scheduledAt,
                        $appointment->meeting_link
                    ),
                ];

                // Also tell the assigned clinician their schedule changed —
                // unless they are the one who performed the reschedule.
                $clinicianUser = $appointment->clinician?->user;
                if ($clinicianUser && $clinicianUser->id !== $request->user()->id) {
                    $created[] = $this->notificationService->appointmentRescheduledForClinician(
                        $clinicianUser->id,
                        $appointment->patient->user->name,
                        $scheduledAt
                    );
                }

                return $created;
            });
        } catch (SlotUnavailableException $e) {
            return back()->withErrors([
                'scheduled_at' => $e->getMessage(),
            ]);
        }

        foreach ($notifications as $notification) {
            SendPushNotification::dispatch($notification->id)->afterCommit();
        }

        return redirect()->route('appointments.index')
            ->with('status', 'Appointment rescheduled.');
    }
}
