<?php

namespace Tests\Integration;

use App\Jobs\MarkOverdueNoShows;
use App\Models\Appointment;
use App\Models\Clinician;
use App\Models\Patient;
use App\Services\AttendanceService;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    private function appointmentFor(Clinician $clinician, Patient $patient, string $status, $scheduledAt): Appointment
    {
        return Appointment::create([
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
            'requested_at' => $scheduledAt,
            'scheduled_at' => $scheduledAt,
            'mode' => 'online',
            'status' => $status,
        ]);
    }

    public function test_conclude_as_attended_marks_completed(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('att-attended@test.com');
        $appt = $this->appointmentFor($clinician['clinician'], $patient['patient'], 'approved', now()->subHour());

        $this->actingAs($clinician['user'], 'web')
            ->patch("/appointments/{$appt->id}/complete", ['outcome' => 'attended'])
            ->assertRedirect(route('appointments.index'));

        $this->assertSame('completed', $appt->fresh()->status);
    }

    public function test_conclude_as_no_show_marks_no_show(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('att-noshow@test.com');
        $appt = $this->appointmentFor($clinician['clinician'], $patient['patient'], 'approved', now()->subHour());

        $this->actingAs($clinician['user'], 'web')
            ->patch("/appointments/{$appt->id}/complete", ['outcome' => 'no_show'])
            ->assertRedirect(route('appointments.index'));

        $this->assertSame('no_show', $appt->fresh()->status);
    }

    public function test_backstop_job_marks_overdue_unconcluded_as_no_show(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('att-overdue@test.com');

        $overdue = $this->appointmentFor($clinician['clinician'], $patient['patient'], 'approved', now()->subHours(25));
        $recent = $this->appointmentFor($clinician['clinician'], $patient['patient'], 'approved', now()->subHours(2));
        $future = $this->appointmentFor($clinician['clinician'], $patient['patient'], 'rescheduled', now()->addDay());

        (new MarkOverdueNoShows)->handle();

        $this->assertSame('no_show', $overdue->fresh()->status, 'overdue session should auto-no-show');
        $this->assertSame('approved', $recent->fresh()->status, 'within grace window — untouched');
        $this->assertSame('rescheduled', $future->fresh()->status, 'future session — untouched');
    }

    public function test_stats_and_consecutive_no_shows(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('att-stats@test.com')['patient'];

        // Chronological history: completed, no_show, completed, no_show, no_show.
        $this->appointmentFor($clinician['clinician'], $patient, 'completed', now()->subDays(5));
        $this->appointmentFor($clinician['clinician'], $patient, 'no_show', now()->subDays(4));
        $this->appointmentFor($clinician['clinician'], $patient, 'completed', now()->subDays(3));
        $this->appointmentFor($clinician['clinician'], $patient, 'no_show', now()->subDays(2));
        $this->appointmentFor($clinician['clinician'], $patient, 'no_show', now()->subDay());

        $stats = (new AttendanceService)->statsFor($patient);

        $this->assertSame(2, $stats['attended']);
        $this->assertSame(3, $stats['no_shows']);
        $this->assertSame(40, $stats['attendance_rate']); // 2 / (2+3)
        $this->assertSame(2, $stats['consecutive_no_shows']); // trailing streak
        $this->assertTrue($stats['at_risk']);
    }

    public function test_completed_session_breaks_the_streak(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('att-recover@test.com')['patient'];

        $this->appointmentFor($clinician['clinician'], $patient, 'no_show', now()->subDays(3));
        $this->appointmentFor($clinician['clinician'], $patient, 'no_show', now()->subDays(2));
        $this->appointmentFor($clinician['clinician'], $patient, 'completed', now()->subDay());

        $stats = (new AttendanceService)->statsFor($patient);

        $this->assertSame(0, $stats['consecutive_no_shows']);
        $this->assertFalse($stats['at_risk']);
    }

    public function test_at_risk_patient_ids_and_list_pill(): void
    {
        $clinician = $this->createClinician();
        $risky = $this->createPatient('att-risky@test.com')['patient'];
        $okay = $this->createPatient('att-okay@test.com')['patient'];

        $this->appointmentFor($clinician['clinician'], $risky, 'no_show', now()->subDays(2));
        $this->appointmentFor($clinician['clinician'], $risky, 'no_show', now()->subDay());
        $this->appointmentFor($clinician['clinician'], $okay, 'completed', now()->subDay());

        $atRisk = (new AttendanceService)->atRiskPatientIds(collect([$risky, $okay]));

        $this->assertArrayHasKey($risky->id, $atRisk);
        $this->assertArrayNotHasKey($okay->id, $atRisk);

        $this->actingAs($this->createAdmin(), 'web')
            ->get('/patients')
            ->assertStatus(200)
            ->assertSee('At risk');
    }
}
