<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\PatientResource;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $patient = auth()->user()->patient;

        if (! $patient) {
            return response()->json(['message' => 'Patient profile not found.'], 404);
        }

        return response()->json([
            'data' => new PatientResource($patient),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $patient = auth()->user()->patient;

        if (! $patient) {
            return response()->json(['message' => 'Patient profile not found.'], 404);
        }

        $patient->update($request->validated());

        return response()->json([
            'data' => new PatientResource($patient->fresh()),
        ]);
    }
}
