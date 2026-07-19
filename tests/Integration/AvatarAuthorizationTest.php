<?php

namespace Tests\Integration;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarAuthorizationTest extends TestCase
{
    /** Give a user a real (faked) avatar file so the serve route returns 200. */
    private function giveAvatar(User $user): void
    {
        $path = UploadedFile::fake()
            ->create('avatar.jpg', 80, 'image/jpeg')
            ->store('avatars', 'local');
        $user->update(['avatar_path' => $path]);
    }

    public function test_clinician_cannot_view_off_caseload_patient_avatar(): void
    {
        Storage::fake('local');
        $clinician = $this->createClinician();
        $patient = $this->createPatient(); // not assigned to this clinician
        $this->giveAvatar($patient['user']);

        // Authorization is refused before the file is ever read.
        $this->actingAs($clinician['user'], 'web')
            ->get(route('avatars.show', $patient['user']))
            ->assertStatus(403);
    }

    public function test_clinician_can_view_own_avatar(): void
    {
        Storage::fake('local');
        $clinician = $this->createClinician();
        $this->giveAvatar($clinician['user']);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('avatars.show', $clinician['user']))
            ->assertStatus(200);
    }

    public function test_clinician_can_view_caseload_patient_avatar(): void
    {
        Storage::fake('local');
        $clinician = $this->createClinician();
        $patient = $this->createPatient();
        $patient['patient']->update(['assigned_clinician_id' => $clinician['clinician']->id]);
        $this->giveAvatar($patient['user']);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('avatars.show', $patient['user']))
            ->assertStatus(200);
    }

    public function test_clinician_patient_list_displays_caseload_avatar(): void
    {
        Storage::fake('local');
        $clinician = $this->createClinician();
        $patient = $this->createPatient('listed-avatar@test.com');
        $patient['patient']->assignedClinicians()->attach($clinician['clinician']->id);
        $this->giveAvatar($patient['user']);

        $user = $patient['user']->fresh();
        $avatarUrl = route('avatars.show', $user).'?v='.$user->updated_at->timestamp;

        $this->actingAs($clinician['user'], 'web')
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee('data-patient-avatar', false)
            ->assertSee($avatarUrl, false)
            ->assertSee($user->name.' profile photo');
    }

    public function test_clinician_patient_list_uses_initials_without_avatar(): void
    {
        $clinician = $this->createClinician();
        $patient = $this->createPatient('listed-initials@test.com');
        $patient['patient']->assignedClinicians()->attach($clinician['clinician']->id);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('patients.index'))
            ->assertOk()
            ->assertSee('data-patient-initials', false)
            ->assertSee('JP');
    }

    public function test_clinician_dashboard_displays_approved_patient_avatar(): void
    {
        Storage::fake('local');
        $clinician = $this->createClinician();
        $patient = $this->createPatient('dashboard-avatar@test.com');
        $patient['patient']->assignedClinicians()->attach($clinician['clinician']->id);
        $this->giveAvatar($patient['user']);

        Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => now()->addDay(),
            'scheduled_at' => now()->addDay(),
            'mode' => 'in_person',
            'status' => 'approved',
        ]);

        $user = $patient['user']->fresh();
        $avatarUrl = route('avatars.show', $user).'?v='.$user->updated_at->timestamp;

        $this->actingAs($clinician['user'], 'web')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-dashboard-patient-avatar', false)
            ->assertSee($avatarUrl, false)
            ->assertSee($user->name.' profile photo');
    }

    public function test_clinician_dashboard_does_not_request_unapproved_patient_avatar(): void
    {
        Storage::fake('local');
        $clinician = $this->createClinician();
        $patient = $this->createPatient('dashboard-pending@test.com');
        $this->giveAvatar($patient['user']);

        Appointment::create([
            'patient_id' => $patient['patient']->id,
            'clinician_id' => $clinician['clinician']->id,
            'requested_at' => now()->addDay(),
            'mode' => 'online',
            'status' => 'pending',
        ]);

        $this->actingAs($clinician['user'], 'web')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-dashboard-patient-initials', false)
            ->assertDontSee(route('avatars.show', $patient['user']), false);
    }

    public function test_admin_can_view_any_avatar(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $patient = $this->createPatient();
        $this->giveAvatar($patient['user']);

        $this->actingAs($admin, 'web')
            ->get(route('avatars.show', $patient['user']))
            ->assertStatus(200);
    }
}
