<?php

namespace Tests\Integration;

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
