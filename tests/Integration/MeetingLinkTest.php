<?php

namespace Tests\Integration;

use App\Models\Appointment;
use App\Services\AppointmentService;
use Tests\TestCase;

class MeetingLinkTest extends TestCase
{
    private function makeAppointment(string $mode): Appointment
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        return Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => now()->addDays(3)->setTime(9, 0),
            'mode' => $mode,
            'status' => 'pending',
        ]);
    }

    public function test_approving_online_appointment_generates_jitsi_link(): void
    {
        $appointment = $this->makeAppointment('online');

        $approved = app(AppointmentService::class)->approve($appointment);

        $base = rtrim(config('services.jitsi.base_url'), '/');
        $this->assertNotNull($approved->meeting_link);
        $this->assertStringStartsWith($base.'/', $approved->meeting_link);
        $this->assertStringContainsString('-'.$approved->id.'-', $approved->meeting_link);
    }

    public function test_approving_in_person_appointment_has_no_link(): void
    {
        $appointment = $this->makeAppointment('in_person');

        $approved = app(AppointmentService::class)->approve($appointment);

        $this->assertNull($approved->meeting_link);
    }

    public function test_meeting_link_active_within_ttl_and_expires_after(): void
    {
        $appointment = $this->makeAppointment('online');
        $approved = app(AppointmentService::class)->approve($appointment, now()->subHour()->toDateTimeString());

        // Scheduled 1h ago → within the 5h window → active.
        $this->assertTrue($approved->meetingLinkActive());
        $this->assertEqualsWithDelta(
            now()->addHours(4)->timestamp,
            $approved->meetingLinkExpiresAt()->timestamp,
            60
        );

        // Move scheduled time to 6h ago → past the 5h window → expired.
        $approved->update(['scheduled_at' => now()->subHours(6)]);
        $this->assertFalse($approved->fresh()->meetingLinkActive());
    }

    public function test_resource_hides_expired_meeting_link(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => now()->subHours(6),
            'scheduled_at' => now()->subHours(6),
            'mode' => 'online',
            'meeting_link' => 'https://meet.jit.si/TheraConnect-1-abc',
            'status' => 'approved',
        ]);

        $this->withHeaders($this->apiHeaders($this->getApiToken($patient['user'])))
            ->getJson("/api/v1/appointments/{$appointment->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.meeting_link', null)
            ->assertJsonPath('data.meeting_link_active', false);
    }

    public function test_resource_exposes_active_meeting_link(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => now()->addHour(),
            'scheduled_at' => now()->addHour(),
            'mode' => 'online',
            'meeting_link' => 'https://meet.jit.si/TheraConnect-1-abc',
            'status' => 'approved',
        ]);

        $this->withHeaders($this->apiHeaders($this->getApiToken($patient['user'])))
            ->getJson("/api/v1/appointments/{$appointment->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.meeting_link', 'https://meet.jit.si/TheraConnect-1-abc')
            ->assertJsonPath('data.meeting_link_active', true);
    }

    public function test_reschedule_keeps_the_same_online_room(): void
    {
        $service = app(AppointmentService::class);
        $appointment = $this->makeAppointment('online');

        $link = $service->approve($appointment)->meeting_link;
        $this->assertNotNull($link);

        $rescheduled = $service->reschedule($appointment->fresh(), now()->addDays(5)->setTime(10, 0)->toDateTimeString());

        $this->assertSame($link, $rescheduled->meeting_link);
    }
}
