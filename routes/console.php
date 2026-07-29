<?php

use App\Jobs\GenerateAppointmentReminders;
use App\Jobs\GenerateAssignmentReminders;
use App\Jobs\MarkOverdueNoShows;
use App\Services\AssignmentFileRetentionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('assignments:purge-expired-files', function (AssignmentFileRetentionService $retention) {
    $purged = $retention->purgeExpired();
    $this->info("Purged {$purged} expired assignment file(s).");
})->purpose('Delete assignment uploads after their retention period');

Schedule::call(function () {
    dispatch(new GenerateAssignmentReminders);
})->hourly();

Schedule::call(function () {
    dispatch(new GenerateAppointmentReminders);
})->dailyAt('08:00');

Schedule::call(function () {
    dispatch(new MarkOverdueNoShows);
})->dailyAt('02:00');

Schedule::command('assignments:purge-expired-files')
    ->dailyAt('03:00')
    ->withoutOverlapping();
