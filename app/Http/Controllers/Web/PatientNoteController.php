<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PatientNoteController extends Controller
{
    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $clinician = $this->currentClinician($request);

        // A clinician may only add notes for a patient on their caseload.
        abort_unless($patient->assigned_clinician_id === $clinician->id, 403);

        $validated = $this->validateNote($request);

        $patient->clinicianNotes()->create([
            'clinician_id' => $clinician->id,
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'],
            'is_shared' => $request->boolean('is_shared'),
        ]);

        return redirect()->route('patients.show', $patient)
            ->with('status', 'Note added.');
    }

    public function update(Request $request, PatientNote $note): RedirectResponse
    {
        Gate::authorize('manage', $note);

        $validated = $this->validateNote($request);

        $note->update([
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'],
            'is_shared' => $request->boolean('is_shared'),
        ]);

        return redirect()->route('patients.show', $note->patient_id)
            ->with('status', 'Note updated.');
    }

    public function destroy(Request $request, PatientNote $note): RedirectResponse
    {
        Gate::authorize('manage', $note);

        $patientId = $note->patient_id;
        $note->delete();

        return redirect()->route('patients.show', $patientId)
            ->with('status', 'Note deleted.');
    }

    private function validateNote(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'is_shared' => ['sometimes', 'boolean'],
        ]);
    }

    private function currentClinician(Request $request)
    {
        $clinician = $request->user()->clinician;

        abort_unless($clinician !== null, 403, 'No clinician profile.');

        return $clinician;
    }
}
