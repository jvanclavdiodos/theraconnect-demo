<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmissionRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\Assignment;
use App\Models\Submission;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $existing = Submission::where('assignment_id', $id)
            ->where('patient_id', $patient->id)
            ->first();

        if ($existing && $existing->status === 'reviewed') {
            return response()->json([
                'message' => 'This submission has already been reviewed and can no longer be changed.',
            ], 409);
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

    public function downloadFile(int $id): StreamedResponse
    {
        $submission = Submission::findOrFail($id);

        // Ownership: a patient may only download their own submission file.
        // Replaces the prior inline `abort_unless($patient && ... === $patient->id)`
        // with the SubmissionPolicy::view Gate, consistent with how the
        // AppointmentController enforces ownership for appointment downloads.
        Gate::authorize('view', $submission);

        abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);

        return Storage::disk('local')->download(
            $submission->file_path,
            $submission->original_name
        );
    }
}
