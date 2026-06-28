<?php

namespace Tests\Integration;

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Clinician;
use App\Models\Notification;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppointmentFlowTest extends TestCase
{
    public function test_patient_can_book_appointment(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 09:00:00',
                'mode' => 'in_person',
                'reason' => 'Initial consultation',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.mode', 'in_person')
            ->assertJsonPath('data.reason', 'Initial consultation');

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'status' => 'pending',
            'mode' => 'in_person',
        ]);
    }

    public function test_patient_can_view_their_appointments(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        // Book an appointment first
        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 10:00:00',
                'mode' => 'online',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/appointments');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.mode', 'online');
    }

    public function test_patient_cannot_view_other_patients_appointments(): void
    {
        $clinician = $this->createClinician();
        $patientA = $this->createPatient('a@test.com');
        $patientB = $this->createPatient('b@test.com');

        $tokenA = $this->getApiToken($patientA['user']);

        $createResponse = $this->withHeaders($this->apiHeaders($tokenA))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 11:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        $appointmentId = $createResponse->json('data.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'patient_id' => $patientA['patient']->id,
        ]);
        $this->assertNotEquals($patientA['patient']->id, $patientB['patient']->id);

        // Patient A can view (actingAs with sanctum guard)
        $this->actingAs($patientA['user'], 'sanctum')
            ->getJson("/api/v1/appointments/{$appointmentId}")
            ->assertOk();

        // Patient B should be forbidden (actingAs with sanctum guard)
        $this->actingAs($patientB['user'], 'sanctum')
            ->getJson("/api/v1/appointments/{$appointmentId}")
            ->assertForbidden();
    }

    public function test_patient_can_cancel_appointment(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $create = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 14:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        $id = $create->json('data.id');

        $response = $this->withHeaders($this->apiHeaders($token))
            ->deleteJson("/api/v1/appointments/{$id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('appointments', [
            'id' => $id,
            'status' => 'cancelled',
        ]);
    }

    public function test_double_cancel_returns_409(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $create = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 15:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        $id = $create->json('data.id');

        // First cancel
        $this->withHeaders($this->apiHeaders($token))
            ->deleteJson("/api/v1/appointments/{$id}");

        // Second cancel
        $this->withHeaders($this->apiHeaders($token))
            ->deleteJson("/api/v1/appointments/{$id}")
            ->assertStatus(409);
    }

    public function test_double_booking_same_slot_is_rejected(): void
    {
        $clinician = $this->createClinician();
        $patientA = $this->createPatient('dbl-a@test.com');
        $patientB = $this->createPatient('dbl-b@test.com');

        // A future whole-hour slot (relative, so the test never rots).
        $slot = now()->addDays(7)->setTime(9, 0)->format('Y-m-d H:i:s');

        // Patient A books the 09:00 slot
        $this->withHeaders($this->apiHeaders($this->getApiToken($patientA['user'])))
            ->postJson('/api/v1/appointments', [
                'requested_at' => $slot,
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ])
            ->assertStatus(201);

        // Patient B attempts the same clinician + slot
        $this->withHeaders($this->apiHeaders($this->getApiToken($patientB['user'])))
            ->postJson('/api/v1/appointments', [
                'requested_at' => $slot,
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.requested_at.0', 'That time slot is already booked.');

        $this->assertEquals(1, Appointment::count());
    }

    public function test_schedule_slots_contain_correct_data(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/schedules?date=2030-12-31');

        $response->assertStatus(200)
            ->assertJsonCount(9, 'data');

        $firstSlot = $response->json('data.0');
        $this->assertArrayHasKey('slot', $firstSlot);
        $this->assertArrayHasKey('clinician_id', $firstSlot);
        $this->assertArrayHasKey('clinician_name', $firstSlot);
        $this->assertArrayHasKey('available', $firstSlot);
        $this->assertTrue($firstSlot['available']);
    }

    public function test_schedule_shows_booked_slot_as_unavailable(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        // Book the 09:00 slot
        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/appointments', [
                'requested_at' => '2030-12-31 09:00:00',
                'mode' => 'in_person',
                'clinician_id' => $clinician['clinician']->id,
            ]);

        // After booking one slot, total slots should remain 9 (all slots still listed, some unavailable)
        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/schedules?date=2030-12-31');

        $response->assertStatus(200)
            ->assertJsonCount(9, 'data');

        // At least one slot should be unavailable
        $hasUnavailable = collect($response->json('data'))->contains('available', false);
        $this->assertTrue($hasUnavailable, 'Expected at least one unavailable slot after booking');
    }

    /**
     * `?date=` must be validated as YYYY-MM-DD BEFORE the service layer
     * Carbon::parse()'s it — otherwise garbage input throws an uncaught
     * exception and surfaces as an opaque 500.
     */
    public function test_schedules_rejects_bad_date_format(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $token = $this->getApiToken($patient['user']);

        $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/schedules?date=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');

        $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/schedules?date=2030-06-15')  // valid ISO date
            ->assertStatus(200)
            ->assertJsonCount(9, 'data');
    }

    /**
     * Verifies that AppointmentService::bookAppointment wraps the slot check +
     * insert inside a DB transaction. Without the transaction, a throw from a
     * post-INSERT hook would leave the row persisted (count = 1). With the
     * transaction, the throw rolls back the INSERT (count = 0).
     */
    public function test_booking_is_atomic_on_post_create_hook_failure(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('atomic-book@test.com');
        $token = $this->getApiToken($patient['user']);

        Appointment::created(function () {
            throw new \RuntimeException('simulated post-create failure');
        });

        try {
            $response = $this->withHeaders($this->apiHeaders($token))
                ->postJson('/api/v1/appointments', [
                    'requested_at' => '2030-12-31 09:00:00',
                    'mode' => 'in_person',
                    'clinician_id' => $clinician['clinician']->id,
                ]);

            // The post-create hook aborts the request — Laravel's exception
            // handler returns a server error response.
            $this->assertGreaterThanOrEqual(
                500,
                $response->status(),
                'Expected the post-create hook to abort the request with a server error.'
            );
        } finally {
            Appointment::flushEventListeners();
        }

        // The critical assertion: the INSERT was rolled back, so no appointment
        // row for this patient was persisted.
        $this->assertEquals(0, Appointment::where('patient_id', $patient['patient']->id)->count());
    }

    /**
     * Verifies that AppointmentService::reschedule wraps the slot check +
     * UPDATE inside a DB transaction. If a post-UPDATE hook throws, the
     * original appointment data must be preserved (UPDATE rolled back).
     */
    public function test_reschedule_is_atomic_on_post_update_hook_failure(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('atomic-resched@test.com');

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        Appointment::updated(function () {
            throw new \RuntimeException('simulated post-update failure');
        });

        try {
            app(AppointmentService::class)->reschedule($appointment, '2030-12-31 14:00:00');
            $this->fail('Expected the post-update hook to abort the reschedule.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('simulated post-update failure', $e->getMessage());
        } finally {
            Appointment::flushEventListeners();
        }

        // The UPDATE must have been rolled back: status is still 'approved'
        // and scheduled_at still points at the original datetime.
        $fresh = $appointment->fresh();
        $this->assertEquals('approved', $fresh->status);
        $this->assertEquals('2030-12-31 09:00:00', $fresh->scheduled_at->format('Y-m-d H:i:s'));
    }

    /**
     * Slot-unavailability during reschedule should throw SlotUnavailableException
     * (handled by the web controller to redirect back with errors). The service
     * method itself enforces this inside the transaction.
     */
    public function test_reschedule_to_taken_slot_throws(): void
    {
        $clinician = $this->createClinician();
        $patientA = $this->createPatient('rsched-a@test.com');
        $patientB = $this->createPatient('rsched-b@test.com');

        // A future day (relative, so the test never rots).
        $day = now()->addDays(7)->format('Y-m-d');

        // Patient A holds an approved booking at 14:00.
        Appointment::create([
            'patient_id' => $patientA['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => "$day 14:00:00",
            'scheduled_at' => "$day 14:00:00",
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        // Patient B has an appointment that we'll try to reschedule to A's slot.
        $b = Appointment::create([
            'patient_id' => $patientB['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => "$day 10:00:00",
            'scheduled_at' => "$day 10:00:00",
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        $this->expectException(SlotUnavailableException::class);
        app(AppointmentService::class)->reschedule($b, "$day 14:00:00");
    }

    /**
     * Verifies the web controller wraps appointment approval + notification
     * creation in a DB transaction. If the NotificationService throws (simulating
     * a notification insert failure), the appointment UPDATE must roll back:
     * status stays 'pending', no notification row, no push job dispatched.
     */
    public function test_web_approve_is_atomic_with_notification_creation(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('web-approve@test.com');

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'pending',
        ]);

        // Swap NotificationService for a fake that throws on appointmentApproved.
        $fakeNotif = $this->partialMock(NotificationService::class);
        $fakeNotif->shouldReceive('appointmentApproved')
            ->once()
            ->andThrow(new \RuntimeException('notif insert failed'));

        $this->app->instance(NotificationService::class, $fakeNotif);

        // Admin approves via the web route (session-authenticated).
        $admin = $this->createAdmin();

        try {
            $this->actingAs($admin, 'web')
                ->patch("/appointments/{$appointment->id}/approve");
        } catch (\RuntimeException $e) {
            // The transaction re-throws — Laravel may convert to 500 response.
        }

        // Critical: appointment status must remain 'pending' (UPDATE rolled back)
        $this->assertEquals('pending', $appointment->fresh()->status);

        // And no notification row + no queued push job.
        $this->assertEquals(0, Notification::where('user_id', $patient['user']->id)->count());
        $this->assertEquals(0, DB::table('jobs')->count());
    }

    /**
     * Verifies the web controller wraps assignment creation + notification in a
     * DB transaction. If NotificationService throws, the assignment must roll
     * back (no assignment row, no notification).
     */
    public function test_web_assignment_store_is_atomic_with_notification_creation(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();
        $patient = $this->createPatient('web-assign@test.com');

        $fakeNotif = $this->partialMock(NotificationService::class);
        $fakeNotif->shouldReceive('assignmentCreated')
            ->once()
            ->andThrow(new \RuntimeException('notif insert failed'));

        $this->app->instance(NotificationService::class, $fakeNotif);

        try {
            $this->actingAs($admin, 'web')
                ->post('/assignments', [
                    'patient_id' => $patient['patient']->id,
                    'clinician_id' => $clinician['clinician']->id,
                    'title' => 'Atomic Test Assignment',
                    'description' => 'Should not persist on notif failure.',
                    'due_date' => '2030-12-31',
                ]);
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertEquals(0, Assignment::where('title', 'Atomic Test Assignment')->count());
        $this->assertEquals(0, Notification::where('user_id', $patient['user']->id)->count());
        $this->assertEquals(0, DB::table('jobs')->count());
    }

    /**
     * Patients may not cancel a `completed` appointment. Cancelling a
     * finalized appointment would desync clinician reporting/analytics and
     * flip a record that the care team considers closed.
     */
    public function test_patient_cannot_cancel_completed_appointment(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('cancel-completed@test.com');
        $token = $this->getApiToken($patient['user']);

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'scheduled_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'completed',
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->deleteJson("/api/v1/appointments/{$appointment->id}")
            ->assertStatus(403);

        $this->assertEquals('completed', $appointment->fresh()->status);
    }

    /**
     * Patients may not cancel a `rejected` appointment (terminal state — same
     * reasoning as `completed`).
     */
    public function test_patient_cannot_cancel_rejected_appointment(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('cancel-rejected@test.com');
        $token = $this->getApiToken($patient['user']);

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 10:00:00',
            'mode' => 'in_person',
            'status' => 'rejected',
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->deleteJson("/api/v1/appointments/{$appointment->id}")
            ->assertStatus(403);

        $this->assertEquals('rejected', $appointment->fresh()->status);
    }

    /**
     * Approving an appointment must link the patient to that clinician so they
     * appear in the caseload and messaging becomes available.
     */
    public function test_approving_appointment_assigns_patient_to_clinician(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'pending',
        ]);

        $this->assertNull($patient['patient']->fresh()->assigned_clinician_id);

        app(AppointmentService::class)->approve($appointment);

        $this->assertEquals(
            $clinician['clinician']->id,
            $patient['patient']->fresh()->assigned_clinician_id
        );
    }

    /**
     * If the patient already has an assigned clinician (e.g. via the sign-up
     * request flow), approving an appointment with a different clinician must
     * NOT overwrite the existing care relationship.
     */
    public function test_approving_appointment_does_not_overwrite_existing_assignment(): void
    {
        $userA = User::create(['name' => 'Dr. A', 'email' => 'dr-a@test.com', 'password' => 'password', 'role' => 'clinician']);
        $clinicianA = Clinician::create(['user_id' => $userA->id, 'license_no' => 'LIC-A', 'specialization' => 'Test']);

        $userB = User::create(['name' => 'Dr. B', 'email' => 'dr-b@test.com', 'password' => 'password', 'role' => 'clinician']);
        $clinicianB = Clinician::create(['user_id' => $userB->id, 'license_no' => 'LIC-B', 'specialization' => 'Test']);

        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinicianA->id]);

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinicianB->id,
            'requested_at' => '2030-12-31 10:00:00',
            'mode' => 'in_person',
            'status' => 'pending',
        ]);

        app(AppointmentService::class)->approve($appointment);

        $this->assertEquals(
            $clinicianA->id,
            $patient['patient']->fresh()->assigned_clinician_id,
            'Existing assignment to clinician A must not be overwritten.'
        );
    }

    /**
     * After a clinician approves an appointment, the patient should appear in
     * that clinician's active patient list.
     */
    public function test_approved_patient_appears_in_clinician_patient_list(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'web')
            ->patch("/appointments/{$appointment->id}/approve")
            ->assertRedirect();

        $this->actingAs($clinician['user'], 'web')
            ->get('/patients')
            ->assertOk()
            ->assertSee($patient['user']->name);
    }

    /**
     * After appointment approval the clinician must be able to open a message
     * thread with the patient (previously forbidden because the caseload link
     * was never written).
     */
    public function test_clinician_can_message_patient_after_appointment_approval(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();
        $patient = $this->createPatient();

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 09:00:00',
            'mode' => 'in_person',
            'status' => 'pending',
        ]);

        // Before approval the clinician has no caseload relationship — messaging is blocked.
        $this->actingAs($clinician['user'], 'web')
            ->post('/messages/open', ['patient_id' => $patient['patient']->id])
            ->assertStatus(403);

        $this->actingAs($admin, 'web')
            ->patch("/appointments/{$appointment->id}/approve")
            ->assertRedirect();

        // After approval the patient is on the caseload — messaging must succeed.
        $this->actingAs($clinician['user'], 'web')
            ->post('/messages/open', ['patient_id' => $patient['patient']->id])
            ->assertRedirect();
    }

    /**
     * Patients may cancel a `cancelled` appointment — the controller's 409
     * ("already cancelled") short-circuit is the expected response, NOT the
     * policy's 403 (otherwise the existing test_double_cancel_returns_409
     * regression test breaks).
     */
    public function test_patient_can_cancel_an_already_cancelled_appointment_for_409(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('cancel-cancelled@test.com');
        $token = $this->getApiToken($patient['user']);

        $appointment = Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => '2030-12-31 11:00:00',
            'mode' => 'in_person',
            'status' => 'cancelled',
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->deleteJson("/api/v1/appointments/{$appointment->id}")
            ->assertStatus(409);
    }
}
