<?php

namespace App\Services;

use App\Events\MessageCreated;
use App\Models\Clinician;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageService
{
    public function __construct(
        private NotificationService $notifications,
        private RealtimeEventDispatcher $realtime,
    ) {}

    /** The single ongoing thread for a patient-clinician pair (created on demand). */
    public function conversationFor(Patient $patient, Clinician $clinician): Conversation
    {
        return Conversation::firstOrCreate([
            'patient_id' => $patient->id,
            'clinician_id' => $clinician->id,
        ]);
    }

    /** @return Collection<int, Clinician> */
    public function assignedCliniciansFor(Patient $patient): Collection
    {
        $ids = $patient->assignedClinicians()->pluck('clinicians.id');

        if ($patient->assigned_clinician_id) {
            $ids->push($patient->assigned_clinician_id);
        }

        return Clinician::with('user')
            ->whereKey($ids->unique()->values())
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, Conversation> */
    public function ensureAssignedConversations(Patient $patient): Collection
    {
        $clinicians = $this->assignedCliniciansFor($patient);

        $clinicians->each(
            fn (Clinician $clinician) => $this->conversationFor($patient, $clinician)
        );

        return Conversation::where('patient_id', $patient->id)
            ->whereIn('clinician_id', $clinicians->pluck('id'))
            ->with(['clinician.user', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderBy('id')
            ->get();
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

            // fill() instead of forceFill() — the columns being set
            // (last_message_at + the per-participant last_read_at) are all
            // listed in Conversation::$fillable, so regular mass assignment
            // works identically without bypassing the guard.
            $conversation->fill([
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

        $this->realtime->dispatch(new MessageCreated($message));
        $this->notifications->dispatchDeliveries($notification);

        return $message;
    }

    /**
     * Total unread messages for a clinician across all their conversations
     * (one query; handles "never read"). Used for the sidebar badge.
     */
    public function clinicianUnreadCount(int $clinicianId, int $userId): int
    {
        return DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.clinician_id', $clinicianId)
            ->where('messages.sender_id', '!=', $userId)
            ->where(function ($q) {
                $q->whereNull('conversations.clinician_last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversations.clinician_last_read_at');
            })
            ->count();
    }

    public function patientUnreadCount(int $patientId, int $userId): int
    {
        return DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.patient_id', $patientId)
            ->where('messages.sender_id', '!=', $userId)
            ->where(function ($query) {
                $query->whereNull('conversations.patient_last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversations.patient_last_read_at');
            })
            ->count();
    }

    /** Mark the conversation read up to now for the given participant. */
    public function markRead(Conversation $conversation, User $reader): void
    {
        $conversation->loadMissing(['patient', 'clinician']);

        $column = $reader->id === $conversation->patient?->user_id
            ? 'patient_last_read_at'
            : 'clinician_last_read_at';

        // fill() (not forceFill): the read-timestamp columns are in $fillable.
        $conversation->fill([$column => now()])->save();
    }
}
