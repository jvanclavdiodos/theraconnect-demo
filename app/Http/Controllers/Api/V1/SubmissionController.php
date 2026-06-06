<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmissionRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\Assignment;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SubmissionController extends Controller
{
    public function __construct(
        private AssignmentService $assignmentService,
    ) {}

    public function store(SubmissionRequest $request, int $id): JsonResponse
    {
        $assignment = Assignment::findOrFail($id);

        Gate::authorize('view', $assignment);

        $patient = auth()->user()->patient;

        if (! $patient) {
            return response()->json(['message' => 'Patient profile not found.'], 404);
        }

        $submission = $this->assignmentService->submit(
            $id,
            $patient->id,
            $request->input('content'),
            $request->file('file'),
        );

        return response()->json([
            'data' => new SubmissionResource($submission),
        ], 201);
    }
}
