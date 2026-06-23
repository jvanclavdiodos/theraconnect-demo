<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TherapyGoalResource;
use Illuminate\Http\JsonResponse;

class GoalController extends Controller
{
    /**
     * The patient's therapy goals (active first), each with its latest GAS
     * rating. Read-only: goals are co-defined and rated by the clinician.
     */
    public function index(): JsonResponse
    {
        $patient = $this->getPatient();

        $goals = $patient->therapyGoals()
            ->whereIn('status', ['active', 'met'])
            ->with('latestRating')
            ->orderByRaw("status = 'active' desc")
            ->latest()
            ->get();

        return response()->json([
            'data' => TherapyGoalResource::collection($goals),
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
