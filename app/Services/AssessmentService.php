<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Clinician;
use App\Models\Patient;
use App\Support\Assessments;

class AssessmentService
{
    /** Assign a standardized questionnaire to a patient (status: pending). */
    public function assign(Patient $patient, Clinician $clinician, string $instrument): Assessment
    {
        return Assessment::create([
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
            'instrument' => $instrument,
            'status' => 'pending',
        ]);
    }

    /**
     * Record a patient's responses, compute the total score, and close the
     * assessment. Responses are assumed already validated (correct length, each
     * 0–3) against the instrument catalog by the caller.
     *
     * @param  array<int, int>  $responses
     */
    public function submit(Assessment $assessment, array $responses): Assessment
    {
        $responses = array_map('intval', array_values($responses));

        $assessment->update([
            'responses' => $responses,
            'score' => Assessments::score($responses),
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $assessment->fresh();
    }
}
