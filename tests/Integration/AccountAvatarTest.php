<?php

namespace Tests\Integration;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountAvatarTest extends TestCase
{
    private function validAvatar(string $name = 'avatar.png'): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    public function test_clinician_account_exposes_crop_controls_and_size_guidance(): void
    {
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('Adjust profile photo')
            ->assertSee('data-avatar-crop-form', false)
            ->assertSee('data-avatar-zoom', false)
            ->assertSee('data-avatar-rotate', false)
            ->assertSee('2 MB or smaller')
            ->assertSee('cropperjs/1.6.2/cropper.min.js', false)
            ->assertSee('js/avatar-cropper.js', false);
    }

    public function test_clinician_uploads_and_serves_avatar(): void
    {
        Storage::fake('local');
        $clinician = $this->createClinician();

        $this->actingAs($clinician['user'], 'web')
            ->post('/account/avatar', [
                'avatar' => $this->validAvatar('me.png'),
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
