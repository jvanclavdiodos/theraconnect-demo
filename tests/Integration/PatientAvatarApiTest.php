<?php

namespace Tests\Integration;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientAvatarApiTest extends TestCase
{
    private function validAvatar(string $name = 'avatar.png'): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    public function test_patient_uploads_and_serves_own_avatar(): void
    {
        Storage::fake('local');
        $patient = $this->createPatient();
        $headers = $this->apiHeaders($this->getApiToken($patient['user']));

        // No avatar yet.
        $this->withHeaders($headers)->getJson('/api/v1/profile/avatar')->assertStatus(404);

        $this->withHeaders($headers)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $this->validAvatar(),
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

    public function test_missing_avatar_returns_validation_error_instead_of_server_error(): void
    {
        $patient = $this->createPatient('missing-avatar@test.com');
        $headers = $this->apiHeaders($this->getApiToken($patient['user']));

        $this->withHeaders($headers)
            ->postJson('/api/v1/profile/avatar')
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar')
            ->assertJsonPath('errors.avatar.0', 'Choose a photo before uploading.');

        $this->assertNull($patient['user']->fresh()->avatar_path);
    }

    public function test_avatar_is_limited_to_two_megabytes(): void
    {
        $patient = $this->createPatient('large-avatar@test.com');
        $headers = $this->apiHeaders($this->getApiToken($patient['user']));

        $this->withHeaders($headers)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->create('large.jpg', 2049, 'image/jpeg'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar')
            ->assertJsonPath('errors.avatar.0', 'The profile photo must not be greater than 2 MB.');
    }

    public function test_portal_missing_avatar_redirects_with_field_error(): void
    {
        $patient = $this->createPatient('portal-missing-avatar@test.com');

        $this->actingAs($patient['user'], 'web')
            ->from(route('portal.profile.show'))
            ->post(route('portal.profile.avatar.update'))
            ->assertRedirect(route('portal.profile.show'))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($patient['user']->fresh()->avatar_path);
    }

    public function test_portal_profile_exposes_crop_controls_and_size_guidance(): void
    {
        $patient = $this->createPatient('portal-cropper@test.com');

        $this->actingAs($patient['user'], 'web')
            ->get(route('portal.profile.show'))
            ->assertOk()
            ->assertSee('Adjust profile photo')
            ->assertSee('data-avatar-zoom', false)
            ->assertSee('data-avatar-rotate', false)
            ->assertSee('2 MB or smaller')
            ->assertSee('cropperjs/1.6.2/cropper.min.js', false)
            ->assertSee('js/avatar-cropper.js', false);
    }
}
