<?php

use App\Jobs\GenerateAppointmentReminders;
use App\Jobs\GenerateAssignmentReminders;
use App\Jobs\MarkOverdueNoShows;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    dispatch(new GenerateAssignmentReminders);
})->hourly();

Schedule::call(function () {
    dispatch(new GenerateAppointmentReminders);
})->dailyAt('08:00');

Schedule::call(function () {
    dispatch(new MarkOverdueNoShows);
})->dailyAt('02:00');
