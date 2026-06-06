<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotification;
use App\Models\Assignment;
use App\Models\Patient;
use App\Models\Submission;
use App\Services\AssignmentService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebAssignmentController extends Controller
{
    public function __construct(
        private AssignmentService $assignmentService,
        private NotificationService $notificationService,
    ) {}

    public function index(): View
    {
        $assignments = Assignment::with(['patient.user', 'clinician.user', 'submissions'])
            ->latest()
            ->paginate(20);

        return view('assignments.index', compact('assignments'));
    }

    public function create(): View
    {
        $patients = Patient::with('user')->orderBy('id')->get();

        return view('assignments.create', compact('patients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $clinician = auth()->user()->clinician;

        if (! $clinician) {
            return back()->withErrors(['auth' => 'Your account does not have a clinician profile.']);
        }

        $validated = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);

        $assignment = $this->assignmentService->create([
            'clinician_id' => $clinician->id,
            'patient_id' => $validated['patient_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
        ]);

        $patient = Patient::with('user')->find($validated['patient_id']);
        $notification = $this->notificationService->assignmentCreated(
            $patient->user->id,
            auth()->user()->name,
            $validated['title']
        );

        SendPushNotification::dispatch($notification->id)->afterCommit();

        return redirect()->route('assignments.index')
            ->with('status', 'Assignment created successfully.');
    }

    public function submissions(Assignment $assignment): View
    {
        $assignment->load('patient.user');
        $submissions = $assignment->submissions()->with('patient.user')->get();

        return view('assignments.submissions', compact('assignment', 'submissions'));
    }

    public function review(Submission $submission): RedirectResponse
    {
        $this->assignmentService->review($submission);

        return back()->with('status', 'Submission marked as reviewed.');
    }
}
