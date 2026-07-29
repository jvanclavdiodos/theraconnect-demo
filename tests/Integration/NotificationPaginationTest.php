<?php

namespace Tests\Integration;

use App\Models\Notification;
use Tests\TestCase;

class NotificationPaginationTest extends TestCase
{
    public function test_clinician_notifications_are_paginated_ten_per_page_and_isolated(): void
    {
        $clinician = $this->createClinician();
        $other = $this->createPatient('notification-pagination-other@test.com');
        $this->createNotifications($clinician['user']->id, 'Clinician');
        $this->notification($clinician['user']->id, 'Hidden message', Notification::MESSAGE_RECEIVED);
        $this->notification($other['user']->id, 'Other user');

        $this->actingAs($clinician['user'])
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Clinician 11')
            ->assertSee('Clinician 02')
            ->assertDontSee('Clinician 01')
            ->assertDontSee('Hidden message')
            ->assertDontSee('Other user');

        $this->actingAs($clinician['user'])
            ->get(route('notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Clinician 01')
            ->assertDontSee('Clinician 02');
    }

    public function test_patient_portal_notifications_are_paginated_ten_per_page(): void
    {
        $patient = $this->createPatient('notification-pagination-portal@test.com');
        $this->createNotifications($patient['user']->id, 'Portal');

        $this->actingAs($patient['user'])
            ->get(route('portal.notifications.index'))
            ->assertOk()
            ->assertSee('Portal 11')
            ->assertSee('Portal 02')
            ->assertDontSee('Portal 01');

        $this->actingAs($patient['user'])
            ->get(route('portal.notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Portal 01')
            ->assertDontSee('Portal 02');
    }

    public function test_patient_api_notifications_are_paginated_ten_per_page(): void
    {
        $patient = $this->createPatient('notification-pagination-api@test.com');
        $this->createNotifications($patient['user']->id, 'API');
        $headers = $this->apiHeaders($this->getApiToken($patient['user']));

        $this->withHeaders($headers)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(Notification::PER_PAGE, 'data')
            ->assertJsonPath('data.0.title', 'API 11')
            ->assertJsonPath('data.9.title', 'API 02')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 11);

        $this->withHeaders($headers)
            ->getJson('/api/v1/notifications?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'API 01')
            ->assertJsonPath('meta.current_page', 2);
    }

    private function createNotifications(int $userId, string $prefix): void
    {
        foreach (range(1, 11) as $number) {
            $this->notification($userId, sprintf('%s %02d', $prefix, $number));
        }
    }

    private function notification(
        int $userId,
        string $title,
        string $type = 'appointment_approved'
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => 'Notification pagination test.',
        ]);
    }
}
