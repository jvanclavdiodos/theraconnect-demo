<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DuplicateMoodLogException;
use App\Http\Controllers\Controller;
use App\Http\Resources\MoodLogResource;
use App\Services\MoodLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodLogController extends Controller
{
    public function __construct(private MoodLogService $moodLogs) {}

    /** Recent mood check-ins for the authenticated patient (newest first). */
    public function index(): JsonResponse
    {
        $patient = $this->getPatient();

        $logs = $patient->moodLogs()
            ->orderByDesc('logged_on')
            ->orderByDesc('created_at')
            ->take(60)
            ->get();
        $today = $this->moodLogs->today();
        $todayLog = $logs->first(fn ($log) => $log->logged_on->toDateString() === $today)
            ?? $this->moodLogs->todayFor($patient, $today);

        return response()->json([
            'data' => MoodLogResource::collection($logs),
            'meta' => [
                'today' => $today,
                'today_completed' => $todayLog !== null,
                'today_log' => $todayLog ? new MoodLogResource($todayLog) : null,
            ],
        ]);
    }

    /**
     * Log a quick mood check-in (1–10). Single-table patient write, so it lives
     * inline here rather than in a Service.
     */
    public function store(Request $request): JsonResponse
    {
        $patient = $this->getPatient();

        $validated = $request->validate([
            'score' => ['required', 'integer', 'between:1,10'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $log = $this->moodLogs->createDaily($patient, $validated);
        } catch (DuplicateMoodLogException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => new MoodLogResource($exception->moodLog),
            ], 409);
        }

        return response()->json([
            'data' => new MoodLogResource($log),
        ], 201);
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
