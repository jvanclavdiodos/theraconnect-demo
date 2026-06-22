<?php

namespace Tests\Integration;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\Appointment;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    public function test_patient_can_view_notifications(): void
    {
        $patient = $this->createPatient('notif@test.com');
        $token = $this->getApiToken($patient['user']);

        // Create a notification
        Notification::create([
            'user_id' => $patient['user']->id,
            'type' => 'appointment_approved',
            'title' => 'Appointment Approved',
            'body' => 'Your appointment on June 10 is confirmed.',
            'data' => json_encode(['appointment_id' => 1]),
            'channel' => 'fcm',
            'sent_at' => now(),
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Appointment Approved')
            ->assertJsonPath('data.0.type', 'appointment_approved');
    }

    public function test_patient_can_mark_notification_as_read(): void
    {
        $patient = $this->createPatient('markread@test.com');
        $token = $this->getApiToken($patient['user']);

        $notification = Notification::create([
            'user_id' => $patient['user']->id,
            'type' => 'assignment_created',
            'title' => 'New Assignment',
            'body' => 'Dr. Test assigned you: Daily Mood Journal.',
            'data' => json_encode(['assignment_id' => 1]),
            'channel' => 'fcm',
        ]);

        $this->assertNull($notification->fresh()->read_at);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('data.type', 'assignment_created');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_patient_cannot_mark_others_notification_read(): void
    {
        $patientA = $this->createPatient('na@test.com');
        $patientB = $this->createPatient('nb@test.com');

        $notification = Notification::create([
            'user_id' => $patientA['user']->id,
            'type' => 'assignment_created',
            'title' => 'New Assignment',
            'body' => 'Test body.',
            'data' => null,
            'channel' => 'fcm',
        ]);

        $tokenB = $this->getApiToken($patientB['user']);

        $this->withHeaders($this->apiHeaders($tokenB))
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertStatus(404);
    }

    public function test_device_token_upsert_works(): void
    {
        $patient = $this->createPatient('device@test.com');
        $token = $this->getApiToken($patient['user']);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/device-token', [
                'token' => 'fcm-test-device-token-xyz',
                'platform' => 'android',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $patient['user']->id,
            'token' => 'fcm-test-device-token-xyz',
            'platform' => 'android',
        ]);
    }

    public function test_device_token_delete_works(): void
    {
        $patient = $this->createPatient('deldevice@test.com');
        $token = $this->getApiToken($patient['user']);

        // Register token
        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/device-token', [
                'token' => 'fcm-to-delete',
                'platform' => 'android',
            ]);

        // Delete token
        $this->withHeaders($this->apiHeaders($token))
            ->deleteJson('/api/v1/device-token', [
                'token' => 'fcm-to-delete',
            ])
            ->assertStatus(204);

        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'fcm-to-delete',
        ]);
    }

    public function test_device_token_update_reassigns_to_current_user(): void
    {
        $patient = $this->createPatient('reassign@test.com');
        $token = $this->getApiToken($patient['user']);

        // First post
        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/device-token', [
                'token' => 'fcm-reassign-token',
                'platform' => 'android',
            ]);

        // Second post (same token, same user) should update, not create duplicate
        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/v1/device-token', [
                'token' => 'fcm-reassign-token',
                'platform' => 'ios',
            ])
            ->assertStatus(201);

        // Should still have only one row for this user+token combination
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $patient['user']->id,
            'token' => 'fcm-reassign-token',
            'platform' => 'ios',
        ]);

        // Count - should be exactly 1
        $count = DeviceToken::where('token', 'fcm-reassign-token')->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Two patients on a shared device (same FCM token) must each be able to
     * register the same token without an HTTP 500. Under the legacy global
     * UNIQUE on `token` alone, the second INSERT violated the constraint and
     * crashed. With the composite UNIQUE on (user_id, token) the second
     * user simply gets a new row referencing the same physical token.
     */
    public function test_shared_device_token_works_across_users(): void
    {
        $patientA = $this->createPatient('shared-a@test.com');
        $patientB = $this->createPatient('shared-b@test.com');

        $sharedToken = 'fcm-shared-device-xyz';

        // Patient A registers the shared token.
        $this->actingAs($patientA['user'], 'sanctum')
            ->postJson('/api/v1/device-token', [
                'token' => $sharedToken,
                'platform' => 'android',
            ])
            ->assertStatus(201);

        // Patient B registers the SAME shared token — must not 500.
        $this->actingAs($patientB['user'], 'sanctum')
            ->postJson('/api/v1/device-token', [
                'token' => $sharedToken,
                'platform' => 'android',
            ])
            ->assertStatus(201);

        // Both users have their own row referencing the shared token.
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $patientA['user']->id,
            'token' => $sharedToken,
        ]);
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $patientB['user']->id,
            'token' => $sharedToken,
        ]);

        $this->assertEquals(2, DeviceToken::where('token', $sharedToken)->count());
    }
}
