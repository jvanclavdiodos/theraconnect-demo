<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\ActivityLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PatientRecordExportController extends Controller
{
    public function __invoke(Request $request, Patient $patient)
    {
        Gate::authorize('exportRecord', $patient);

        $patient->load([
            'user', 'assignedClinicians.user', 'appointments.clinician.user',
            'assessments.clinician.user', 'assignments.clinician.user',
            'assignments.submissions', 'moodLogs', 'therapyGoals.clinician.user',
            'therapyGoals.ratings', 'clinicianNotes.clinician.user',
        ]);

        $bytes = Pdf::loadView('patients.record-pdf', compact('patient'))->setPaper('a4')->output();
        app(ActivityLogService::class)->log($request->user(), 'patient.record_exported', $patient);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="patient-record-'.$patient->id.'-'.now()->format('Y-m-d').'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
