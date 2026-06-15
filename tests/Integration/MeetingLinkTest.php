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
        $this->assertStringStartsWith($base . '/', $approved->meeting_link);
        $this->assertStringContainsString('-' . $approved->id . '-', $approved->meeting_link);
    }

    public function test_approving_in_person_appointment_has_no_link(): void
    {
        $appointment = $this->makeAppointment('in_person');

        $approved = app(AppointmentService::class)->approve($appointment);

        $this->assertNull($approved->meeting_link);
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
