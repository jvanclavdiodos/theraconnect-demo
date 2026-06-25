<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Services\ActivityLogService;
use App\Services\AssessmentService;
use App\Support\Assessments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortalAssessmentController extends Controller
{
    public function __construct(private AssessmentService $assessments) {}

    public function index(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $assessments = Assessment::where('patient_id', $patient->id)
            ->orderByRaw("status = 'pending' desc")
            ->latest()
            ->get();

        return view('portal.assessments.index', compact('assessments'));
    }

    public function show(Assessment $assessment): View
    {
        Gate::authorize('view', $assessment);

        $definition = Assessments::definition($assessment->instrument);

        return view('portal.assessments.show', [
            'assessment' => $assessment,
            'definition' => $definition,
            'options' => Assessments::OPTIONS,
        ]);
    }

    public function submit(Request $request, Assessment $assessment): RedirectResponse
    {
        Gate::authorize('complete', $assessment);

        if ($assessment->status === 'completed') {
            return redirect()
                ->route('portal.assessments.show', $assessment)
                ->with('status', 'This questionnaire has already been completed.');
        }

        $count = Assessments::itemCount($assessment->instrument);

        $validated = $request->validate([
            'responses' => ['required', 'array', "size:{$count}"],
            'responses.*' => ['required', 'integer', 'between:0,3'],
        ]);

        $assessment = $this->assessments->submit($assessment, $validated['responses']);

        app(ActivityLogService::class)->log($request->user(), 'assessment.submitted', $assessment);

        return redirect()
            ->route('portal.assessments.show', $assessment)
            ->with('status', 'Questionnaire submitted. Thank you.');
    }
}
