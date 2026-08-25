<?php

namespace Tests\Integration;

use App\Models\Assignment;
use Tests\TestCase;

class StaffAssignmentSearchTest extends TestCase
{
    public function test_staff_can_search_assignments_without_bypassing_clinician_scope(): void
    {
        $owner = $this->createClinician();
        $owner['user']->update(['email' => 'assignment-owner-clinician@test.com']);
        $other = $this->createClinician();
        $other['user']->update(['email' => 'assignment-other-clinician@test.com']);
        $patient = $this->createPatient('staff-assignment-search@test.com');

        Assignment::create([
            'clinician_id' => $owner['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Searchable Exercise',
        ]);
        Assignment::create([
            'clinician_id' => $owner['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Different Exercise',
        ]);
        Assignment::create([
            'clinician_id' => $other['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Searchable Private Exercise',
        ]);

        $this->actingAs($owner['user'])
            ->get(route('assignments.index', ['search' => 'Searchable']))
            ->assertOk()
            ->assertSee('Searchable Exercise')
            ->assertDontSee('Different Exercise')
            ->assertDontSee('Searchable Private Exercise');

        $this->actingAs($this->createAdmin())
            ->get(route('assignments.index', ['search' => 'Searchable']))
            ->assertOk()
            ->assertSee('Searchable Exercise')
            ->assertSee('Searchable Private Exercise');
    }
}
