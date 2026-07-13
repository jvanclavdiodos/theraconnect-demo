<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var array<int, int> */
    private array $recipientUserIds;

    public function __construct(
        private readonly Appointment $appointment,
        private readonly string $change,
    ) {
        $appointment->loadMissing(['patient', 'clinician']);

        $this->recipientUserIds = collect([
            $appointment->patient?->user_id,
            $appointment->clinician?->user_id,
        ])->filter()->unique()->values()->all();
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            ...array_map(
                fn (int $userId) => new PrivateChannel('users.'.$userId),
                $this->recipientUserIds
            ),
            new PrivateChannel('admin.appointments'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'appointment.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'status' => $this->appointment->status,
            'change' => $this->change,
            'updated_at' => $this->appointment->updated_at?->toISOString(),
        ];
    }
}
