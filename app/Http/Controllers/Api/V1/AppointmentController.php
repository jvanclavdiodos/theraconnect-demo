<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\ScheduleSlotResource;
use App\Jobs\SendPushNotification;
use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentController extends Controller
{
    public function __construct(
        private AppointmentService $appointmentService,
        private NotificationService $notificationService,
        private AvailabilityService $availabilityService,
    ) {}

    public function schedules(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->format('Y-m-d'));

        // Validate the query param BEFORE Carbon::parse() throws an uncaught
        // exception on garbage input (which would surface as a 500 to the
        // patient). `date_format:Y-m-d` accepts the same shape the service
        // expects and rejects anything else with a 422.
        $validator = validator([
            'date' => $date,
            'clinician_id' => $request->query('clinician_id'),
        ], [
            'date' => ['required', 'date_format:Y-m-d'],
            'clinician_id' => ['nullable', 'integer', 'exists:clinicians,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid query parameters.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $slots = collect($this->appointmentService->getScheduleSlots($date));

        // Clinician-first booking: optionally narrow to one clinician.
        if ($clinicianId = $request->query('clinician_id')) {
            $slots = $slots->where('clinician_id', (int) $clinicianId)->values();
        }

        return response()->json([
            'data' => ScheduleSlotResource::collection($slots),
        ]);
    }

    /**
     * Dates a clinician has at least one open slot, across [from, to] — used to
     * enable/disable days in the patient's booking calendar. Range is capped to
     * keep the response and the per-day loop bounded.
     */
    public function availability(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'clinician_id' => ['required', 'integer', 'exists:clinicians,id'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid query parameters.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $from = Carbon::parse($request->query('from'));
        $to = Carbon::parse($request->query('to'));

        // Cap the window to 62 days so a hostile request can't sweep years.
        if ($from->diffInDays($to) > 62) {
            $to = $from->copy()->addDays(62);
        }

        $dates = $this->availabilityService->openDates(
            (int) $request->query('clinician_id'),
            $from,
            $to
        );

        return response()->json(['data' => $dates]);
    }

    public function index(): JsonResponse
    {
        $patient = $this->getPatient();

        $appointments = Appointment::where('patient_id', $patient->id)
            ->with('clinician.user')
            ->latest('requested_at')
            ->paginate(20);

        return response()->json([
            'data' => AppointmentResource::collection($appointments),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'total' => $appointments->total(),
            ],
        ]);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $patient = $this->getPatient();

        try {
            $appointment = $this->appointmentService->bookAppointment([
                'patient_id' => $patient->id,
                'clinician_id' => $request->clinician_id,
                'requested_at' => $request->requested_at,
                'mode' => $request->mode,
                'reason' => $request->reason,
            ]);
        } catch (SlotUnavailableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'requested_at' => ['That time slot is already booked.'],
                ],
            ], 422);
        }

        $appointment->load('clinician.user', 'patient.user');

        // Tell the assigned clinician a new request is awaiting their approval.
        // Bookings without a clinician (clinician_id is nullable) have no one to
        // notify. The in-app row is written synchronously; the push is best-effort.
        if ($appointment->clinician && $appointment->clinician->user) {
            $notification = $this->notificationService->appointmentRequested(
                $appointment->clinician->user->id,
                $appointment->patient->user->name,
                $appointment->requested_at->format('M d, Y h:i A'),
            );

            SendPushNotification::dispatch($notification->id)->afterCommit();
        }

        return response()->json([
            'data' => new AppointmentResource($appointment),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $appointment = Appointment::with('clinician.user')->findOrFail($id);

        Gate::authorize('view', $appointment);

        return response()->json([
            'data' => new AppointmentResource($appointment),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        Gate::authorize('delete', $appointment);

        if ($appointment->status === 'cancelled') {
            return response()->json([
                'message' => 'This appointment is already cancelled.',
                'data' => new AppointmentResource($appointment->load('clinician.user')),
            ], 409);
        }

        $appointment = $this->appointmentService->cancel($appointment);
        $appointment->load('clinician.user');

        return response()->json([
            'data' => new AppointmentResource($appointment),
        ]);
    }

    private function getPatient()
    {
        $patient = auth()->user()->patient;

        if (! $patient) {
            abort(response()->json(['message' => 'Patient profile not found.'], 404));
        }

        return $patient;
    }
}
