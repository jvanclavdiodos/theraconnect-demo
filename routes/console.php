<?php

use App\Jobs\ExpirePendingAppointments;
use App\Jobs\GenerateAppointmentReminders;
use App\Jobs\GenerateAssignmentReminders;
use App\Jobs\MarkOverdueNoShows;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('push:check {email?}', function (?string $email = null) {
    $projectId = config('services.fcm.project_id');
    $credentialsPath = config('services.fcm.credentials_path');
    $credentials = null;

    if ($credentialsPath && is_file($credentialsPath) && is_readable($credentialsPath)) {
        $credentials = json_decode((string) file_get_contents($credentialsPath), true);
    }

    $checks = [
        'FCM project ID configured' => filled($projectId),
        'FCM credentials path configured' => filled($credentialsPath),
        'FCM credentials file readable' => is_array($credentials),
        'Credentials contain client email' => filled($credentials['client_email'] ?? null),
        'Credentials contain private key' => filled($credentials['private_key'] ?? null),
        'Configured and credential project IDs match' => is_array($credentials)
            && filled($projectId)
            && hash_equals((string) $projectId, (string) ($credentials['project_id'] ?? '')),
    ];

    foreach ($checks as $label => $passed) {
        $passed ? $this->info("PASS: {$label}") : $this->error("FAIL: {$label}");
    }

    if ($email) {
        $user = User::where('email', strtolower($email))->first();
        if (! $user) {
            $this->error('FAIL: No user found for that email.');
        } else {
            $count = DeviceToken::where('user_id', $user->id)->count();
            $count > 0
                ? $this->info("PASS: User has {$count} registered device token(s).")
                : $this->error('FAIL: User has no registered device token. Sign in again on the device.');
        }
    }

    return in_array(false, $checks, true) ? Command::FAILURE : Command::SUCCESS;
})->purpose('Verify push configuration without exposing credentials or device tokens');

Artisan::command('storage:check', function () {
    $diskName = config('filesystems.default');
    $path = 'healthchecks/'.Str::uuid().'.txt';
    $contents = Str::random(64);

    try {
        $disk = Storage::disk($diskName);
        $disk->put($path, $contents);

        if (! $disk->exists($path) || $disk->get($path) !== $contents) {
            $this->error("Private storage disk [{$diskName}] failed its read-after-write check.");

            return Command::FAILURE;
        }

        $disk->delete($path);

        if ($disk->exists($path)) {
            $this->error("Private storage disk [{$diskName}] failed to delete its health-check object.");

            return Command::FAILURE;
        }

        $this->info("Private storage disk [{$diskName}] passed write, read, and delete checks.");

        return Command::SUCCESS;
    } catch (Throwable $exception) {
        report($exception);
        $this->error("Private storage disk [{$diskName}] is unavailable: {$exception->getMessage()}");

        return Command::FAILURE;
    } finally {
        try {
            Storage::disk($diskName)->delete($path);
        } catch (Throwable) {
            // Preserve the original health-check result.
        }
    }
})->purpose('Verify that the configured private upload disk can write, read, and delete');

Schedule::call(function () {
    dispatch(new GenerateAssignmentReminders);
})->hourly();

Schedule::call(function () {
    dispatch(new GenerateAppointmentReminders(GenerateAppointmentReminders::DAY_BEFORE));
})->dailyAt('08:00');

Schedule::call(function () {
    dispatch(new GenerateAppointmentReminders(GenerateAppointmentReminders::NIGHT_BEFORE));
})->dailyAt('20:00');

Schedule::call(function () {
    dispatch(new MarkOverdueNoShows);
})->dailyAt('02:00');

Schedule::call(function () {
    dispatch(new ExpirePendingAppointments);
})->everyMinute();
