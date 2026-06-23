<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use App\Models\Clinician;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortalAppointmentController extends Controller
{
    public function __construct(
        private AppointmentService $appointments,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $appointments = Appointment::where('patient_id', $patient->id)
            ->with('clinician.user')
            ->latest('requested_at')
            ->paginate(15);

        return view('portal.appointments.index', compact('appointments'));
    }

    public function show(Appointment $appointment): View
    {
        Gate::authorize('view', $appointment);
        $appointment->load('clinician.user');

        return view('portal.appointments.show', compact('appointment'));
    }

    /**
     * Clinician-first booking. Step 1: choose a clinician. Step 2 (?clinician_id):
     * choose a date. Step 3 (?date): the open slots for that clinician/date are
     * rendered as bookable buttons.
     */
    public function book(Request $request): View
    {
        $validated = $request->validate([
            'clinician_id' => ['nullable', 'integer', 'exists:clinicians,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $clinicians = Clinician::with('user')
            ->join('users', 'users.id', '=', 'clinicians.user_id')
            ->orderBy('users.name')
            ->select('clinicians.*')
            ->get();

        $selectedClinician = null;
        $slots = [];

        if (! empty($validated['clinician_id'])) {
            $selectedClinician = $clinicians->firstWhere('id', (int) $validated['clinician_id']);
        }

        if ($selectedClinician && ! empty($validated['date'])) {
            $slots = collect($this->appointments->getScheduleSlots($validated['date']))
                ->where('clinician_id', $selectedClinician->id)
                ->values()
                ->all();
        }

        $date = $validated['date'] ?? null;

        return view('portal.appointments.book', compact('clinicians', 'selectedClinician', 'slots', 'date'));
    }

    public function store(Request $request): RedirectResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $validated = $request->validate([
            'requested_at' => ['required', 'date', 'after:now'],
            'mode' => ['required', 'in:in_person,online'],
            'reason' => ['nullable', 'string', 'max:500'],
            'clinician_id' => ['required', 'exists:clinicians,id'],
        ]);

        try {
            $appointment = DB::transaction(function () use ($patient, $validated) {
                $appt = $this->appointments->bookAppointment([
                    'patient_id' => $patient->id,
                    'clinician_id' => $validated['clinician_id'],
                    'requested_at' => $validated['requested_at'],
                    'mode' => $validated['mode'],
                    'reason' => $validated['reason'] ?? null,
                ]);

                $appt->load('clinician.user', 'patient.user');

                if ($appt->clinician && $appt->clinician->user) {
                    $notification = $this->notifications->appointmentRequested(
                        $appt->clinician->user->id,
                        $appt->patient->user->name,
                        $appt->requested_at->format('M d, Y h:i A'),
                    );
                    SendPushNotification::dispatch($notification->id)->afterCommit();
                }

                return $appt;
            });
        } catch (SlotUnavailableException $e) {
            return back()->withErrors(['requested_at' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('portal.appointments.show', $appointment)
            ->with('status', 'Appointment requested — your clinician will confirm it shortly.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        Gate::authorize('delete', $appointment);

        if ($appointment->status === 'cancelled') {
            return back()->with('status', 'This appointment is already cancelled.');
        }

        $this->appointments->cancel($appointment);

        return redirect()
            ->route('portal.appointments.index')
            ->with('status', 'Appointment cancelled.');
    }
}
