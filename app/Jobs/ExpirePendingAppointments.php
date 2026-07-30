<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpirePendingAppointments implements ShouldQueue
{
    use Queueable;

    public function handle(AppointmentService $appointments): void
    {
        Appointment::where('status', 'pending')
            ->where('requested_at', '<=', now())
            ->eachById(fn (Appointment $appointment) => $appointments->expire($appointment));
    }
}
