<?php

namespace App\Support;

final class UserGuide
{
    public const VERSION = '1.0';

    public static function forRole(string $role): array
    {
        return match ($role) {
            'patient' => [
                ['title' => 'Book and manage appointments', 'description' => 'Choose a clinician and an available schedule. Review appointment status and cancel eligible bookings from Appointments.', 'action' => 'appointments'],
                ['title' => 'Message your care team', 'description' => 'After a clinician approves an appointment, that clinician is added to your care team. You can message each clinician you have booked with.', 'action' => 'messages'],
                ['title' => 'Complete assignments', 'description' => 'Read instructions, download any worksheet, and submit your response before the deadline.', 'action' => 'assignments'],
                ['title' => 'Track your progress', 'description' => 'Complete PHQ-9 and GAD-7 questionnaires, record mood check-ins, and review goals and shared notes.', 'action' => 'progress'],
                ['title' => 'Keep your account current', 'description' => 'Update your profile, photo, and password from your account page.', 'action' => 'profile'],
            ],
            'clinician' => [
                ['title' => 'Review appointment requests', 'description' => 'Approve, reject, reschedule, and complete requests. Approval adds you to the patient\'s care team without replacing other clinicians.', 'action' => 'appointments'],
                ['title' => 'Work with your caseload', 'description' => 'Patients shows only people assigned to you. Open a record to review permitted clinical information and progress.', 'action' => 'patients'],
                ['title' => 'Create and review assignments', 'description' => 'Create assignments for assigned patients and review submitted work.', 'action' => 'assignments'],
                ['title' => 'Message assigned patients', 'description' => 'Communicate with patients in your caseload. Conversations remain participant-only.', 'action' => 'messages'],
                ['title' => 'Monitor progress', 'description' => 'Assign questionnaires, review results, manage goals, and add shared or private notes.', 'action' => 'patients'],
            ],
            default => [],
        };
    }
}
