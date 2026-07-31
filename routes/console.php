<?php

use App\Jobs\ExpirePendingAppointments;
use App\Jobs\GenerateAppointmentReminders;
use App\Jobs\GenerateAssignmentReminders;
use App\Jobs\MarkOverdueNoShows;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
