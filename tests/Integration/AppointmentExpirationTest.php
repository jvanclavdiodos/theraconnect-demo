<?php

namespace Tests\Integration;

use App\Exceptions\InvalidStateException;
use App\Jobs\ExpirePendingAppointments;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Tests\Concerns\CreatesActors;
use Tests\TestCase;

class AppointmentExpirationTest extends TestCase
{
    use CreatesActors;

    public function test_job_expires_only_pending_requests_at_or_before_their_requested_time(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('expiry-job@test.com');

        $expired = $this->appointment($patient['patient']->id, $clinician['clinician']->id, 'pending', now()->subMinute());
        $future = $this->appointment($patient['patient']->id, $clinician['clinician']->id, 'pending', now()->addMinute());
        $approved = $this->appointment($patient['patient']->id, $clinician['clinician']->id, 'approved', now()->subMinute());

        $job = new ExpirePendingAppointments;
        $job->handle(app(AppointmentService::class));
        $job->handle(app(AppointmentService::class));

        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('pending', $future->fresh()->status);
        $this->assertSame('approved', $approved->fresh()->status);
    }

    public function test_expired_request_releases_the_clinician_slot(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('expiry-slot@test.com');
        $slot = now()->addWeek()->startOfHour();

        $appointment = $this->appointment(
            $patient['patient']->id,
            $clinician['clinician']->id,
            'expired',
            $slot
        );

        $this->assertTrue(
            app(AppointmentService::class)->isSlotAvailable(
                $clinician['clinician']->id,
                $slot->format('Y-m-d H:i:s'),
                $appointment->id + 1
            )
        );
    }

    public function test_elapsed_pending_request_cannot_be_approved_or_rejected(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('expiry-transition@test.com');
        $appointments = app(AppointmentService::class);

        $approve = $this->appointment($patient['patient']->id, $clinician['clinician']->id, 'pending', now()->subSecond());

        try {
            $appointments->approve($approve);
            $this->fail('Expected approval of an elapsed request to be refused.');
        } catch (InvalidStateException $exception) {
            $this->assertSame('This appointment request has expired.', $exception->getMessage());
        }

        $reject = $this->appointment($patient['patient']->id, $clinician['clinician']->id, 'pending', now()->subSecond());

        try {
            $appointments->reject($reject);
            $this->fail('Expected rejection of an elapsed request to be refused.');
        } catch (InvalidStateException $exception) {
            $this->assertSame('This appointment request has expired.', $exception->getMessage());
        }

        $this->assertSame('pending', $approve->fresh()->status);
        $this->assertSame('pending', $reject->fresh()->status);
    }

    public function test_clinician_pending_filter_hides_elapsed_requests_before_the_job_runs(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('expiry-filter@test.com');

        $elapsed = $this->appointment($patient['patient']->id, $clinician['clinician']->id, 'pending', now()->subMinute());
        $future = $this->appointment($patient['patient']->id, $clinician['clinician']->id, 'pending', now()->addHour());

        $this->actingAs($clinician['user'])
            ->get(route('appointments.index', ['status' => 'pending']))
            ->assertOk()
            ->assertViewHas('appointments', function ($appointments) use ($elapsed, $future): bool {
                return $appointments->contains('id', $future->id)
                    && ! $appointments->contains('id', $elapsed->id);
            });
    }

    public function test_elapsed_request_is_shown_as_expired_without_approval_actions(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('expiry-display@test.com');

        $this->appointment(
            $patient['patient']->id,
            $clinician['clinician']->id,
            'pending',
            now()->subMinute()
        );

        $this->actingAs($clinician['user'])
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('Request expired')
            ->assertSee('Expired')
            ->assertDontSee('aria-label="Approve appointment"', false)
            ->assertDontSee('aria-label="Reject appointment"', false);
    }

    private function appointment(
        int $patientId,
        int $clinicianId,
        string $status,
        \DateTimeInterface $requestedAt,
    ): Appointment {
        return Appointment::create([
            'patient_id' => $patientId,
            'clinician_id' => $clinicianId,
            'requested_at' => $requestedAt,
            'scheduled_at' => $status === 'approved' ? $requestedAt : null,
            'mode' => 'in_person',
            'status' => $status,
        ]);
    }
}
