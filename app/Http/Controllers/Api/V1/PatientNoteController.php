<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientNoteResource;
use App\Models\PatientNote;
use Illuminate\Http\JsonResponse;

class PatientNoteController extends Controller
{
    /** Notes the clinician has shared with this patient (read-only). */
    public function index(): JsonResponse
    {
        $patient = $this->getPatient();

        $notes = PatientNote::where('patient_id', $patient->id)
            ->where('is_shared', true)
            ->with('clinician.user')
            ->latest()
            ->get();

        return response()->json([
            'data' => PatientNoteResource::collection($notes),
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
