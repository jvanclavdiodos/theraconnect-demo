<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Clinician;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageService
{
    public function __construct(private NotificationService $notifications) {}

    /** The single ongoing thread for a patient-clinician pair (created on demand). */
    public function conversationFor(Patient $patient, Clinician $clinician): Conversation
    {
        return Conversation::firstOrCreate([
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
        ]);
    }

    /**
     * Post a message into a conversation, mark it read for the sender, and
     * notify the other participant (in-app row synchronously + best-effort push).
     * The write + notification row share a transaction; the push fires after
     * commit (mirrors WebAppointmentController).
     */
    public function send(Conversation $conversation, User $sender, string $body): Message
    {
        $conversation->loadMissing(['patient.user', 'clinician.user']);

        [$message, $notification] = DB::transaction(function () use ($conversation, $sender, $body) {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            $isPatient = $sender->id === $conversation->patient->user_id;

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                // The sender has implicitly read up to their own message.
                ($isPatient ? 'patient_last_read_at' : 'clinician_last_read_at') => $message->created_at,
            ])->save();

            $recipientUserId = $isPatient
                ? $conversation->clinician->user_id
                : $conversation->patient->user_id;

            $notification = $this->notifications->messageReceived(
                $recipientUserId,
                $sender->name,
                Str::limit($body, 120)
            );

            return [$message, $notification];
        });

        SendPushNotification::dispatch($notification->id)->afterCommit();

        return $message;
    }

    /** Mark the conversation read up to now for the given participant. */
    public function markRead(Conversation $conversation, User $reader): void
    {
        $conversation->loadMissing(['patient', 'clinician']);

        $column = $reader->id === $conversation->patient?->user_id
            ? 'patient_last_read_at'
            : 'clinician_last_read_at';

        $conversation->forceFill([$column => now()])->save();
    }
}
