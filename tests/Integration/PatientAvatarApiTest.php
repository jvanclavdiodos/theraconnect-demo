<?php

namespace Tests\Integration;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientAvatarApiTest extends TestCase
{
    public function test_patient_uploads_and_serves_own_avatar(): void
    {
        Storage::fake('local');
        $patient = $this->createPatient();
        $headers = $this->apiHeaders($this->getApiToken($patient['user']));

        // No avatar yet.
        $this->withHeaders($headers)->getJson('/api/v1/profile/avatar')->assertStatus(404);

        $this->withHeaders($headers)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('me.jpg', 800, 800),
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.has_avatar', true);

        $this->assertNotNull($patient['user']->fresh()->avatar_path);

        $this->withHeaders($headers)->getJson('/api/v1/profile/avatar')->assertStatus(200);
    }

    public function test_rejects_non_image(): void
    {
        Storage::fake('local');
        $patient = $this->createPatient();

        $this->withHeaders($this->apiHeaders($this->getApiToken($patient['user'])))
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->create('x.pdf', 60, 'application/pdf'),
            ])
            ->assertStatus(422);
    }
}
