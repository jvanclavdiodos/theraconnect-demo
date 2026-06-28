<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PatientNote;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalNoteController extends Controller
{
    /** Notes the clinician has explicitly shared with this patient (read-only). */
    public function index(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $notes = PatientNote::where('patient_id', $patient->id)
            ->where('is_shared', true)
            ->with('clinician.user')
            ->latest()
            ->get();

        return view('portal.notes.index', compact('notes'));
    }
}
