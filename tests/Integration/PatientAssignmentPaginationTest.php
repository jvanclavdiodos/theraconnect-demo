<?php

namespace Tests\Integration;

use App\Models\Assignment;
use Tests\TestCase;

class PatientAssignmentPaginationTest extends TestCase
{
    public function test_patient_assignments_are_paginated_ten_per_page_and_isolated(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('assignment-pagination@test.com');
        $other = $this->createPatient('assignment-pagination-other@test.com');

        foreach (range(1, 11) as $number) {
            Assignment::create([
                'clinician_id' => $clinician['clinician']->id,
                'patient_id' => $patient['patient']->id,
                'title' => sprintf('Patient assignment %02d', $number),
            ]);
        }

        Assignment::create([
            'clinician_id' => $clinician['clinician']->id,
            'patient_id' => $other['patient']->id,
            'title' => 'Other patient assignment',
        ]);

        $this->actingAs($patient['user'])
            ->get(route('portal.assignments.index'))
            ->assertOk()
            ->assertViewHas('assignments', fn ($assignments) => $assignments->count() === 10
                && $assignments->perPage() === 10
                && $assignments->total() === 11
                && $assignments->lastPage() === 2)
            ->assertSee('page=2', false)
            ->assertDontSee('Other patient assignment');

        $this->actingAs($patient['user'])
            ->get(route('portal.assignments.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('assignments', fn ($assignments) => $assignments->count() === 1)
            ->assertDontSee('Other patient assignment');
    }

    public function test_patient_list_pages_reserve_bottom_space_for_joy(): void
    {
        $patient = $this->createPatient('joy-list-spacing@test.com');

        foreach ([
            'portal.appointments.index',
            'portal.assignments.index',
            'portal.notifications.index',
        ] as $route) {
            $this->actingAs($patient['user'])
                ->get(route($route))
                ->assertOk()
                ->assertSee('class="tc-has-joy"', false);
        }
    }
}
