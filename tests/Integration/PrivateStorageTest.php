<?php

namespace Tests\Integration;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateStorageTest extends TestCase
{
    public function test_storage_check_verifies_the_configured_private_disk(): void
    {
        Storage::fake('s3');
        config()->set('filesystems.default', 's3');

        $this->artisan('storage:check')
            ->expectsOutput('Private storage disk [s3] passed write, read, and delete checks.')
            ->assertSuccessful();

        Storage::disk('s3')->assertDirectoryEmpty('healthchecks');
    }

    public function test_s3_uploads_are_private_and_fail_loudly(): void
    {
        $this->assertSame('private', config('filesystems.disks.s3.visibility'));
        $this->assertTrue(config('filesystems.disks.s3.throw'));
        $this->assertTrue(config('filesystems.disks.s3.report'));
    }

    public function test_avatar_upload_uses_the_configured_cloud_disk(): void
    {
        Storage::fake('s3');
        config()->set('filesystems.default', 's3');
        $clinician = $this->createClinician();
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );

        $this->actingAs($clinician['user'], 'web')
            ->post('/account/avatar', [
                'avatar' => UploadedFile::fake()->createWithContent('avatar.png', $png),
            ])
            ->assertRedirect();

        Storage::disk('s3')->assertExists($clinician['user']->fresh()->avatar_path);
    }
}
