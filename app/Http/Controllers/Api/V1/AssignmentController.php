<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    public function index(): JsonResponse
    {
        $patient = auth()->user()->patient;

        if (! $patient) {
            return response()->json(['message' => 'Patient profile not found.'], 404);
        }

        $assignments = Assignment::where('patient_id', $patient->id)
            ->with(['clinician.user', 'submissions'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => AssignmentResource::collection($assignments),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'total' => $assignments->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $assignment = Assignment::with(['clinician.user', 'submissions'])->findOrFail($id);

        Gate::authorize('view', $assignment);

        return response()->json([
            'data' => new AssignmentResource($assignment),
        ]);
    }

    public function downloadWorksheet(int $id): StreamedResponse
    {
        $assignment = Assignment::findOrFail($id);

        Gate::authorize('view', $assignment);

        abort_unless(
            $assignment->attachment_path && Storage::disk('local')->exists($assignment->attachment_path),
            404
        );

        return Storage::disk('local')->download(
            $assignment->attachment_path,
            $assignment->attachment_name
        );
    }
}
