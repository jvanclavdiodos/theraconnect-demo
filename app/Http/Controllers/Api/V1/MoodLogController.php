<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MoodLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodLogController extends Controller
{
    /** Recent mood check-ins for the authenticated patient (newest first). */
    public function index(): JsonResponse
    {
        $patient = $this->getPatient();

        $logs = $patient->moodLogs()
            ->latest()
            ->take(60)
            ->get();

        return response()->json([
            'data' => MoodLogResource::collection($logs),
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

        $log = $patient->moodLogs()->create($validated);

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
