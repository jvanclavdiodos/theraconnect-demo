<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmissionRequest;
use App\Models\Assignment;
use App\Models\Submission;
use App\Services\AssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalAssignmentController extends Controller
{
    public function __construct(private AssignmentService $assignments) {}

    public function index(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $assignments = Assignment::where('patient_id', $patient->id)
            ->with(['clinician.user', 'submissions'])
            ->latest()
            ->paginate(15);

        return view('portal.assignments.index', compact('assignments'));
    }

    public function show(Assignment $assignment): View
    {
        Gate::authorize('view', $assignment);
        $assignment->load(['clinician.user', 'submissions']);

        return view('portal.assignments.show', compact('assignment'));
    }

    public function downloadWorksheet(Assignment $assignment): StreamedResponse
    {
        Gate::authorize('view', $assignment);

        abort_unless(
            $assignment->attachment_path && Storage::disk()->exists($assignment->attachment_path),
            404
        );

        return Storage::disk()->download(
            $assignment->attachment_path,
            $assignment->attachment_name
        );
    }

    public function submit(SubmissionRequest $request, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('view', $assignment);

        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $existing = Submission::where('assignment_id', $assignment->id)
            ->where('patient_id', $patient->id)
            ->first();

        if ($existing && $existing->status === 'reviewed') {
            return back()->with('status', 'This submission has already been reviewed and can no longer be changed.');
        }

        $this->assignments->submit(
            $assignment->id,
            $patient->id,
            $request->input('content'),
            $request->file('file'),
        );

        return redirect()
            ->route('portal.assignments.show', $assignment)
            ->with('status', 'Submission saved.');
    }

    public function downloadSubmission(Submission $submission): StreamedResponse
    {
        Gate::authorize('view', $submission);

        abort_unless(
            $submission->file_path && Storage::disk()->exists($submission->file_path),
            404
        );

        return Storage::disk()->download(
            $submission->file_path,
            $submission->original_name
        );
    }
}
