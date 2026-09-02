<?php

namespace Tests\Integration;

use App\Models\Assignment;
use Tests\TestCase;

class StaffAssignmentSearchTest extends TestCase
{
    public function test_clinician_assignments_are_paginated_ten_per_page_and_isolated(): void
    {
        $owner = $this->createClinician();
        $owner['user']->update(['email' => 'assignment-page-owner@test.com']);
        $other = $this->createClinician();
        $other['user']->update(['email' => 'assignment-page-other@test.com']);
        $patient = $this->createPatient('staff-assignment-page@test.com');

        foreach (range(1, 11) as $number) {
            Assignment::create([
                'clinician_id' => $owner['clinician']->id,
                'patient_id' => $patient['patient']->id,
                'title' => sprintf('Owner assignment %02d', $number),
            ]);
        }

        Assignment::create([
            'clinician_id' => $other['clinician']->id,
            'patient_id' => $patient['patient']->id,
            'title' => 'Other clinician assignment',
        ]);

        $this->actingAs($owner['user'])
            ->get(route('assignments.index'))
            ->assertOk()
            ->assertViewHas('assignments', fn ($assignments) => $assignments->count() === 10
                && $assignments->perPage() === 10
                && $assignments->total() === 11
                && $assignments->lastPage() === 2)
            ->assertSee('page=2', false)
            ->assertDontSee('Owner assignment 01')
            ->assertDontSee('Other clinician assignment');

        $this->actingAs($owner['user'])
            ->get(route('assignments.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('assignments', fn ($assignments) => $assignments->count() === 1)
            ->assertSee('Owner assignment 01')
            ->assertDontSee('Other clinician assignment');
    }

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
