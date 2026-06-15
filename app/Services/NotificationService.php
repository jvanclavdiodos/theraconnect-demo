<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public function create(int $userId, string $type, string $title, string $body, ?array $data = null): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'channel' => 'fcm',
        ]);
    }

    public function appointmentApproved(int $userId, string $scheduledAt, ?string $meetingLink = null): Notification
    {
        return $this->create(
            $userId,
            'appointment_approved',
            'Appointment Approved',
            "Your appointment on {$scheduledAt} is confirmed.",
            $meetingLink ? ['meeting_link' => $meetingLink] : null
        );
    }

    public function appointmentRejected(int $userId): Notification
    {
        return $this->create(
            $userId,
            'appointment_rejected',
            'Appointment Update',
            'Your requested appointment was not approved. Please rebook or contact the clinic.',
            null
        );
    }

    public function appointmentRescheduled(int $userId, string $scheduledAt, ?string $meetingLink = null): Notification
    {
        return $this->create(
            $userId,
            'appointment_rescheduled',
            'Appointment Rescheduled',
            "Your appointment is now set for {$scheduledAt}.",
            $meetingLink ? ['meeting_link' => $meetingLink] : null
        );
    }

    public function appointmentReminder(int $userId, int $appointmentId, string $time): Notification
    {
        return $this->create(
            $userId,
            'appointment_reminder',
            'Appointment Reminder',
            "Reminder: you have an appointment tomorrow at {$time}.",
            ['appointment_id' => $appointmentId]
        );
    }

    public function assignmentCreated(int $userId, string $clinicianName, string $title): Notification
    {
        return $this->create(
            $userId,
            'assignment_created',
            'New Assignment',
            "{$clinicianName} assigned you: {$title}.",
            null
        );
    }

    public function assignmentDeadline(int $userId, int $assignmentId, string $title, string $dueDate): Notification
    {
        return $this->create(
            $userId,
            'assignment_deadline',
            'Assignment Due Soon',
            "'{$title}' is due {$dueDate}.",
            ['assignment_id' => $assignmentId]
        );
    }
}
