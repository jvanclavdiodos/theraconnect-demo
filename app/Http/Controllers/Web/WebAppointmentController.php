<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
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

        $validStatus = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,completed,cancelled,rescheduled,no_show'],
        ])['status'] ?? null;

        if ($validStatus) {
            $query->where('status', $validStatus);
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

    /**
     * Record the session outcome from the post-meeting wrap-up prompt:
     * 'attended' closes the case (completed), 'no_show' records that the patient
     * missed it (feeds attendance / engagement tracking).
     */
    public function complete(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('manage', $appointment);

        if (! in_array($appointment->status, ['approved', 'rescheduled'], true)) {
            return back()->withErrors([
                'status' => 'Only an approved appointment can be concluded.',
            ]);
        }

        // Default to 'attended' so the existing one-click "close case" still
        // works. Validate the outcome so a crafted payload can't set an
        // unsupported status that would otherwise silently fall through to
        // "attended" below.
        $outcome = $request->validate([
            'outcome' => ['nullable', 'string', 'in:attended,no_show'],
        ])['outcome'] ?? 'attended';

        if ($outcome === 'no_show') {
            $this->appointmentService->markNoShow($appointment);

            return redirect()->route('appointments.index')
                ->with('status', 'Appointment recorded as a no-show.');
        }

        $this->appointmentService->complete($appointment);

        return redirect()->route('appointments.index')
            ->with('status', 'Appointment marked as completed.');
    }

    /**
     * Open slots for the appointment's clinician on a given date, so the
     * reschedule picker only offers valid times (mirrors the patient booking UX).
     */
    public function rescheduleSlots(Request $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('manage', $appointment);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'slots' => $this->appointmentService->availableSlotsForReschedule($appointment, $validated['date']),
        ]);
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
