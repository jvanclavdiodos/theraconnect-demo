<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Services\AssessmentService;
use App\Support\Assessments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssessmentController extends Controller
{
    public function __construct(private AssessmentService $assessments) {}

    /** The patient's questionnaires — pending first, then completed history. */
    public function index(): JsonResponse
    {
        $patient = $this->getPatient();

        $assessments = Assessment::where('patient_id', $patient->id)
            ->orderByRaw("status = 'pending' desc")
            ->latest()
            ->get();

        return response()->json([
            'data' => AssessmentResource::collection($assessments),
        ]);
    }

    /** Full questionnaire (items + options) so the app can render the form. */
    public function show(Assessment $assessment): JsonResponse
    {
        Gate::authorize('view', $assessment);

        $def = Assessments::definition($assessment->instrument);

        return response()->json([
            'data' => array_merge(
                (new AssessmentResource($assessment))->toArray(request()),
                [
                    'prompt' => $def['prompt'],
                    'options' => Assessments::OPTIONS,
                    'items' => $def['items'],
                    'responses' => $assessment->responses,
                ],
            ),
        ]);
    }

    /** Submit responses, compute + store the score, close the assessment. */
    public function submit(Request $request, Assessment $assessment): JsonResponse
    {
        Gate::authorize('complete', $assessment);

        if ($assessment->status === 'completed') {
            return response()->json([
                'message' => 'This questionnaire has already been completed.',
            ], 409);
        }

        $count = Assessments::itemCount($assessment->instrument);

        $validated = $request->validate([
            'responses' => ['required', 'array', "size:{$count}"],
            'responses.*' => ['required', 'integer', 'between:0,3'],
        ]);

        $assessment = $this->assessments->submit($assessment, $validated['responses']);

        return response()->json([
            'data' => new AssessmentResource($assessment),
        ], 200);
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
