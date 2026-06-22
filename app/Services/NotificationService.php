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

    /**
     * Sent to the assigned clinician when a patient books a new appointment
     * that is waiting for their approval.
     */
    public function appointmentRequested(int $clinicianUserId, string $patientName, string $requestedAt): Notification
    {
        return $this->create(
            $clinicianUserId,
            'appointment_requested',
            'New Appointment Request',
            "{$patientName} requested an appointment for {$requestedAt}.",
            null
        );
    }

    /**
     * Sent to the assigned clinician when their appointment is rescheduled
     * (e.g. by an admin), so they know their schedule changed.
     */
    public function appointmentRescheduledForClinician(int $clinicianUserId, string $patientName, string $scheduledAt): Notification
    {
        return $this->create(
            $clinicianUserId,
            'appointment_rescheduled',
            'Appointment Rescheduled',
            "{$patientName}'s appointment is now set for {$scheduledAt}.",
            null
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

    /**
     * Sent to the other participant when a new direct message arrives.
     */
    public function messageReceived(int $userId, string $senderName, string $snippet): Notification
    {
        return $this->create(
            $userId,
            'message_received',
            "New message from {$senderName}",
            $snippet,
            null
        );
    }

    /**
     * Sent to the patient when their clinician assigns a standardized
     * questionnaire (PHQ-9 / GAD-7) to complete in the app.
     */
    public function assessmentAssigned(int $userId, string $instrumentTitle): Notification
    {
        return $this->create(
            $userId,
            'assessment_assigned',
            'New questionnaire to complete',
            "Your clinician asked you to complete the {$instrumentTitle}.",
            null
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
