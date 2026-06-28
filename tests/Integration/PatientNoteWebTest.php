<?php

namespace Tests\Integration;

use App\Models\Clinician;
use App\Models\User;
use Tests\TestCase;

class PatientNoteWebTest extends TestCase
{
    private function makeClinician(string $email): array
    {
        $user = User::create([
            'name' => 'Dr. '.ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'password' => 'password',
            'role' => 'clinician',
        ]);
        $clinician = Clinician::create([
            'user_id' => $user->id,
            'license_no' => 'LIC-'.strtoupper(substr(md5($email), 0, 6)),
            'specialization' => 'CBT',
        ]);

        return ['user' => $user, 'clinician' => $clinician];
    }

    /** A clinician with a patient assigned to them. */
    private function caseload(): array
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        return ['clinician' => $clinician, 'patient' => $patient];
    }

    public function test_clinician_adds_note_for_caseload_patient(): void
    {
        $c = $this->caseload();

        $this->actingAs($c['clinician']['user'], 'web')
            ->post("/patients/{$c['patient']['patient']->id}/notes", [
                'title' => 'Prescription',
                'body' => 'Sertraline 50mg daily',
                'is_shared' => '1',
            ])
            ->assertRedirect(route('patients.show', $c['patient']['patient']));

        $this->assertDatabaseHas('patient_notes', [
            'patient_id' => $c['patient']['patient']->id,
            'clinician_id' => $c['clinician']['clinician']->id,
            'title' => 'Prescription',
            'is_shared' => true,
        ]);
    }

    public function test_clinician_cannot_add_note_for_non_caseload_patient(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient(); // not assigned

        $this->actingAs($clinician['user'], 'web')
            ->post("/patients/{$patient['patient']->id}/notes", ['body' => 'x'])
            ->assertStatus(403);
    }

    public function test_clinician_edits_and_deletes_own_note(): void
    {
        $c = $this->caseload();
        $note = $c['patient']['patient']->clinicianNotes()->create([
            'clinician_id' => $c['clinician']['clinician']->id,
            'body' => 'original',
            'is_shared' => false,
        ]);

        $this->actingAs($c['clinician']['user'], 'web')
            ->put("/patient-notes/{$note->id}", ['body' => 'updated', 'is_shared' => '1'])
            ->assertRedirect();
        $this->assertDatabaseHas('patient_notes', ['id' => $note->id, 'body' => 'updated', 'is_shared' => true]);

        $this->actingAs($c['clinician']['user'], 'web')
            ->delete("/patient-notes/{$note->id}")
            ->assertRedirect();
        $this->assertSoftDeleted('patient_notes', ['id' => $note->id]);
    }

    public function test_clinician_cannot_edit_another_clinicians_note(): void
    {
        $c = $this->caseload();
        $other = $this->makeClinician('other@test.com');

        $note = $c['patient']['patient']->clinicianNotes()->create([
            'clinician_id' => $c['clinician']['clinician']->id,
            'body' => 'mine',
        ]);

        $this->actingAs($other['user'], 'web')
            ->put("/patient-notes/{$note->id}", ['body' => 'hijack'])
            ->assertStatus(403);
    }

    public function test_show_page_renders_notes_section(): void
    {
        $c = $this->caseload();

        $this->actingAs($c['clinician']['user'], 'web')
            ->get(route('patients.show', $c['patient']['patient']))
            ->assertStatus(200)
            ->assertSee('Clinician Notes');
    }
}
