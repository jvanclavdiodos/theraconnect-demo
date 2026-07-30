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

    /** @var list<string> */
    private array $recipientUserPublicIds;

    public function __construct(
        private readonly Appointment $appointment,
        private readonly string $change,
    ) {
        $appointment->loadMissing(['patient.user', 'clinician.user']);

        $this->recipientUserPublicIds = collect([
            $appointment->patient?->user?->public_id,
            $appointment->clinician?->user?->public_id,
        ])->filter()->unique()->values()->all();
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            ...array_map(
                fn (string $publicId) => new PrivateChannel('users.'.$publicId),
                $this->recipientUserPublicIds
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
            'appointment_public_id' => $this->appointment->public_id,
            'status' => $this->appointment->status,
            'change' => $this->change,
            'updated_at' => $this->appointment->updated_at?->toISOString(),
        ];
    }
}
