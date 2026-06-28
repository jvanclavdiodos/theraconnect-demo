<?php

namespace Tests\Integration;

use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    public function test_viewing_patient_profile_creates_activity_log(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('patients.show', $patient['patient']))
            ->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $clinician['user']->id,
            'event' => 'patient.viewed',
            'target_type' => 'Patient',
            'target_id' => $patient['patient']->id,
        ]);
    }

    public function test_admin_can_view_activity_audit_page(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'web')
            ->get(route('activity-logs.index'))
            ->assertStatus(200)
            ->assertSee('Activity Audit');
    }

    public function test_clinician_cannot_view_activity_audit_page(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->get(route('activity-logs.index'))
            ->assertStatus(403);
    }

    public function test_activity_log_service_records_event(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();

        app(ActivityLogService::class)->log(
            $admin,
            'test.event',
            $clinician['clinician'],
            ['key' => 'value']
        );

        $log = ActivityLog::first();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('test.event', $log->event);
        $this->assertSame('Clinician', $log->target_type);
        $this->assertSame($clinician['clinician']->id, $log->target_id);
        $this->assertSame(['key' => 'value'], $log->meta);
    }
}
