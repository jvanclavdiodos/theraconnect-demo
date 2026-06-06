<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\ScheduleSlotResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentController extends Controller
{
    public function __construct(
        private AppointmentService $appointmentService,
    ) {}

    public function schedules(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->format('Y-m-d'));

        $slots = $this->appointmentService->getScheduleSlots($date);

        return response()->json([
            'data' => ScheduleSlotResource::collection(collect($slots)),
        ]);
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

        $appointment = $this->appointmentService->create([
            'patient_id' => $patient->id,
            'clinician_id' => $request->clinician_id,
            'requested_at' => $request->requested_at,
            'mode' => $request->mode,
            'reason' => $request->reason,
        ]);

        $appointment->load('clinician.user');

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
