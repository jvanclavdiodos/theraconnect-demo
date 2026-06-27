<?php

namespace Tests\Integration;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountAvatarTest extends TestCase
{
    public function test_clinician_uploads_and_serves_avatar(): void
    {
        Storage::fake('local');
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->post('/account/avatar', [
                'avatar' => UploadedFile::fake()->image('me.jpg', 800, 800),
            ])
            ->assertRedirect();

        $user = $clinician['user']->fresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('local')->assertExists($user->avatar_path);

        // Serve route returns it inline.
        $this->actingAs($clinician['user'], 'web')
            ->get(route('avatars.show', $user))
            ->assertStatus(200);
    }

    public function test_serve_404_when_no_avatar(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->get(route('avatars.show', $clinician['user']))
            ->assertStatus(404);
    }

    public function test_rejects_non_image(): void
    {
        Storage::fake('local');
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->post('/account/avatar', [
                'avatar' => UploadedFile::fake()->create('notes.pdf', 80, 'application/pdf'),
            ])
            ->assertSessionHasErrors('avatar');
    }

    public function test_patient_cannot_access_account(): void
    {
        $patient = $this->createPatient();

        $this->actingAs($patient['user'], 'web')
            ->get('/account')
            ->assertStatus(403);
    }
}
