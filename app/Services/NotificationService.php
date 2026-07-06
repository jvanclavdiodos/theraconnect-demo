<?php

namespace App\Services;

use App\Jobs\SendEmailNotification;
use App\Jobs\SendPushNotification;
use App\Models\Notification;

class NotificationService
{
    public const EMAIL_TYPES = [
        'appointment_requested',
        'appointment_approved',
        'appointment_rejected',
        'appointment_rescheduled',
        'appointment_cancelled',
        'appointment_reminder',
        'patient_request',
        'patient_request_approved',
        'patient_request_denied',
        'assessment_assigned',
        'assignment_created',
        'assignment_deadline',
    ];

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

    public function dispatchDeliveries(Notification $notification): void
    {
        SendPushNotification::dispatch($notification->id)->afterCommit();

        if ($this->shouldEmail($notification)) {
            SendEmailNotification::dispatch($notification->id)->afterCommit();
        }
    }

    public function shouldEmail(Notification $notification): bool
    {
        return in_array($notification->type, self::EMAIL_TYPES, true);
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

    /**
     * Sent to the assigned clinician when a patient cancels their own
     * appointment, so the clinician's calendar isn't left expecting a
     * no-show. The patient already knows (they performed the action); this
     * is the symmetric notification to the other party.
     */
    public function appointmentCancelledByPatient(int $clinicianUserId, string $patientName, string $requestedAt): Notification
    {
        return $this->create(
            $clinicianUserId,
            'appointment_cancelled',
            'Appointment Cancelled',
            "{$patientName} cancelled their appointment for {$requestedAt}.",
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
     * Sent to a clinician when a self-registering patient asks to be added to
     * their caseload, so the clinician can approve or deny the request.
     */
    public function patientRequestSubmitted(int $clinicianUserId, string $patientName): Notification
    {
        return $this->create(
            $clinicianUserId,
            'patient_request',
            'New patient request',
            "{$patientName} requested to be added to your caseload. Review and approve or decline.",
            null
        );
    }

    /**
     * Sent to the patient when a clinician approves their request — they are
     * now connected and can book appointments and message the clinician.
     */
    public function patientRequestApproved(int $patientUserId, string $clinicianName): Notification
    {
        return $this->create(
            $patientUserId,
            'patient_request_approved',
            'Clinician request approved',
            "You're now connected with {$clinicianName}. You can book appointments and send messages.",
            null
        );
    }

    /**
     * Sent to the patient when a clinician declines their request, so they can
     * choose another clinician or contact the clinic.
     */
    public function patientRequestDenied(int $patientUserId): Notification
    {
        return $this->create(
            $patientUserId,
            'patient_request_denied',
            'Clinician request update',
            'Your clinician request was not approved. Please choose another clinician or contact the clinic.',
            null
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
