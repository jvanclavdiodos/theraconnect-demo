<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAssignmentRequest;
use App\Jobs\SendPushNotification;
use App\Models\Assignment;
use App\Models\Clinician;
use App\Models\Patient;
use App\Models\Submission;
use App\Services\AssignmentService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WebAssignmentController extends Controller
{
    public function __construct(
        private AssignmentService $assignmentService,
        private NotificationService $notificationService,
    ) {}

    public function index(Request $request): View
    {
        $query = Assignment::with(['patient.user', 'clinician.user', 'submissions'])
            ->latest();

        // Clinicians see only assignments they authored; admins see all.
        $user = $request->user();
        if ($user->role === 'clinician' && $user->clinician) {
            $query->where('clinician_id', $user->clinician->id);
        }

        $assignments = $query->paginate(20);

        return view('assignments.index', compact('assignments'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        // A clinician may only assign to their own patients and is auto-attributed
        // as the author (no clinician picker). An admin picks both.
        if ($user->role === 'clinician' && $user->clinician) {
            $patients = Patient::with('user')
                ->where('assigned_clinician_id', $user->clinician->id)
                ->orderBy('id')->get();
            $clinicians = collect();
        } else {
            $patients = Patient::with('user')->orderBy('id')->get();
            $clinicians = Clinician::with('user')->orderBy('id')->get();
        }

        return view('assignments.create', compact('patients', 'clinicians'));
    }

    public function store(StoreAssignmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // A clinician authors their own assignments; an admin must attribute
        // the assignment to a clinician via the form (clinician_id validated
        // by StoreAssignmentRequest when the authed user has no clinician
        // profile — i.e. an admin).
        $clinician = auth()->user()->clinician
            ?? Clinician::with('user')->find($validated['clinician_id'] ?? null);

        // A clinician may only create assignments for patients assigned to them
        // (admins pass). Stops a crafted patient_id targeting another's patient.
        Gate::authorize('view', Patient::findOrFail($validated['patient_id']));

        $notification = DB::transaction(function () use ($validated, $request, $clinician) {
            $assignment = $this->assignmentService->create([
                'clinician_id' => $clinician->id,
                'patient_id' => $validated['patient_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
            ], $request->file('attachment'));

            $patient = Patient::with('user')->find($validated['patient_id']);
            $clinician->loadMissing('user');

            return $this->notificationService->assignmentCreated(
                $patient->user->id,
                $clinician->user->name,
                $validated['title']
            );
        });

        SendPushNotification::dispatch($notification->id)->afterCommit();

        return redirect()->route('assignments.index')
            ->with('status', 'Assignment created successfully.');
    }

    public function submissions(Assignment $assignment): View
    {
        Gate::authorize('manage', $assignment);

        $assignment->load('patient.user');
        $submissions = $assignment->submissions()->with('patient.user')->get();

        return view('assignments.submissions', compact('assignment', 'submissions'));
    }

    public function review(Submission $submission): RedirectResponse
    {
        Gate::authorize('review', $submission);

        $this->assignmentService->review($submission);

        return back()->with('status', 'Submission marked as reviewed.');
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

    /**
     * Serve a submission file INLINE (Content-Disposition: inline) so the
     * browser renders it inside an <img>/<iframe> preview instead of forcing a
     * download. Same private disk + ownership gate as downloadSubmission.
     */
    public function previewSubmission(Submission $submission): StreamedResponse
    {
        Gate::authorize('view', $submission);

        abort_unless(
            $submission->file_path && Storage::disk()->exists($submission->file_path),
            404
        );

        return Storage::disk()->response(
            $submission->file_path,
            $submission->original_name
        );
    }

    public function downloadWorksheet(Assignment $assignment): StreamedResponse
    {
        Gate::authorize('manage', $assignment);

        abort_unless(
            $assignment->attachment_path && Storage::disk()->exists($assignment->attachment_path),
            404
        );

        return Storage::disk()->download(
            $assignment->attachment_path,
            $assignment->attachment_name
        );
    }
}
