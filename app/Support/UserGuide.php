<?php

namespace App\Support;

final class UserGuide
{
    public const VERSION = '2.0';

    public static function forRole(string $role): array
    {
        return match ($role) {
            'patient' => self::patientSections(),
            'clinician' => self::clinicianSections(),
            default => [],
        };
    }

    private static function patientSections(): array
    {
        return [
            self::section('Book an appointment', 'Choose a clinician, available date and time, meeting type, and optional reason for the visit.', 'appointments', 'Make sure your profile and contact details are current before requesting an appointment.', [
                'Open Appointments from the navigation menu.',
                'Select Book Appointment.',
                'Choose a clinician. Their specialization appears below their name when available.',
                'Choose a date to load that clinician\'s available times.',
                'Select one available time slot. If none appear, choose another date.',
                'Choose In person or Online (video).',
                'Optionally enter a short reason. Share only information you are comfortable providing.',
                'Review the selections and select Confirm booking once.',
                'Return to My Appointments and confirm that the request is marked Pending.',
            ], 'The appointment remains Pending until the clinician approves, rejects, or reschedules it.', [
                'A slot can become unavailable if another patient books it first. Choose another open time if this happens.',
                'An online meeting link appears in appointment details when an approved session is ready to join.',
            ]),
            self::section('Review or cancel an appointment', 'Check appointment status, clinician contact details, schedule changes, and eligible cancellation options.', 'appointments', null, [
                'Open Appointments.',
                'Use Status, Meeting type, and Date order to narrow the list, then select Apply.',
                'Select the appointment date or View details.',
                'Review its status, effective date and time, clinician, meeting type, reason, and clinician notes.',
                'For an approved online appointment, use Join Video Call when the meeting link is active.',
                'To cancel an eligible pending, approved, or rescheduled appointment, select Cancel appointment.',
                'Confirm the cancellation and verify that the status changes to Cancelled.',
            ], 'Cancelled appointments remain in history but are no longer active.', [
                'Expired, rejected, completed, and already cancelled appointments cannot be cancelled again.',
                'Contact the clinic when a late change cannot be completed through the portal.',
            ]),
            self::section('Submit or update an assignment', 'Read the instructions, download any worksheet, and submit a written response, file, or both.', 'assignments', 'Prepare the requested response. Supported files are PDF, DOC, DOCX, TXT, RTF, JPG, JPEG, or PNG up to 10 MB.', [
                'Open Assignments.',
                'Select an assignment marked To do.',
                'Read the title, clinician, due date, and full instructions.',
                'If a worksheet is attached, select Download worksheet and complete it.',
                'Under Your Submission, type a response, choose a file, or provide both.',
                'Check that the selected file is correct and within the 10 MB limit.',
                'Select Submit and wait for the saved submission to appear.',
                'Return to Assignments and confirm that the status is Submitted.',
                'Before clinician review, reopen the assignment and use Update submission to make a correction.',
            ], 'The assignment shows Submitted with its submission time. A Reviewed submission can no longer be changed.', [
                'Do not upload unrelated personal or clinical documents.',
                'Keep a local copy of important work before uploading it.',
            ]),
            self::section('Message a clinician in your care team', 'Send private messages to clinicians assigned to your care team.', 'messages', 'The clinician must be assigned to your care team before a conversation becomes available.', [
                'Open Messages.',
                'Choose the clinician from the conversation list.',
                'Confirm the clinician name in the conversation header.',
                'Enter the message in the composer at the bottom.',
                'Select Send once. The message appears after the server accepts it.',
                'Keep the conversation open to receive replies in realtime.',
                'If an offline notice appears, reconnect and select Try again. Saved mobile messages may remain visible while offline.',
            ], 'The message appears once in the conversation and the clinician receives a message alert.', [
                'Messaging is not an emergency service. Use emergency or crisis services for immediate danger.',
                'Do not send passwords, financial information, or unrelated sensitive documents.',
            ]),
            self::section('Complete your daily mood check-in', 'Record one brief mood score for the current Manila calendar day and review recent entries.', 'progress', 'Mood check-ins are available once per day and cannot be edited after submission.', [
                'Open Progress, then Mood Check-ins.',
                'Move the slider from 1 (Very low) to 10 (Great).',
                'Optionally add a short note that gives context for the day.',
                'Review the score and note before saving.',
                'Select Save today\'s check-in once.',
                'Confirm that Today\'s check-in is complete appears with the selected score.',
                'Review Recent check-ins to see earlier entries under their correct dates.',
            ], 'One check-in is stored for today using Asia/Manila as the authoritative date.', [
                'A mood score supports reflection and progress tracking; it is not a diagnosis.',
                'Discuss concerning patterns or worsening symptoms with your clinician.',
            ]),
            self::section('Complete a PHQ-9 or GAD-7 questionnaire', 'Answer an assigned standardized questionnaire and review its explanatory result.', 'progress', 'Complete questionnaires privately and answer based on the period stated in the form.', [
                'Open Progress or Questionnaires.',
                'Select an item marked To complete.',
                'Read the purpose, instructions, and response period.',
                'Select one response for every required question.',
                'Review the answers and submit the questionnaire.',
                'Wait for the completed result and severity explanation.',
                'Discuss the result with your clinician, especially when symptoms interfere with daily life.',
            ], 'The questionnaire is marked Done and its score becomes available for progress review.', [
                'PHQ-9 and GAD-7 are screening and monitoring tools, not standalone diagnoses.',
                'Seek immediate help if you may harm yourself or another person; do not wait for a portal reply.',
            ]),
            self::section('Review notifications and unread activity', 'Use notification and navigation badges to find appointment, assignment, assessment, and message updates.', 'notifications', null, [
                'Check the bell icon for unread general notifications.',
                'Open Notifications to review appointment, assignment, assessment, and account updates.',
                'Mark individual items as read, or use Mark all as read when appropriate.',
                'Check the Messages badge separately for unread conversations.',
                'Open the related section to confirm the authoritative appointment, assignment, or conversation status.',
            ], 'Read items no longer contribute to the unread badge, while their underlying records remain available.', [
                'Android notification-bar delivery requires notification permission and an internet connection.',
            ]),
            self::section('Update your profile, photo, or password', 'Keep contact information current and protect account access.', 'profile', 'Prepare a clear JPG or PNG image no larger than 2 MB when changing your photo.', [
                'Open your profile from the picture or name in the top navigation.',
                'Select Edit Profile and update permitted personal or contact information.',
                'To change the photo, choose an image, adjust the crop, and apply it before uploading.',
                'Save and confirm that the updated information appears.',
                'To change the password, open Change Password.',
                'Enter the current password, then enter and confirm a strong new password that meets the displayed rules.',
                'Save the password change and use the new password on the next login.',
            ], 'Saved profile details and the cropped photo appear across authorized portal views.', [
                'Never share your password. Sign out when using a shared device.',
            ]),
        ];
    }

    private static function clinicianSections(): array
    {
        return [
            self::section('Review an appointment request', 'Approve or reject a pending request after reviewing its patient, schedule, meeting type, and reason.', 'appointments', 'Confirm that the requested time is appropriate and the patient is within your authorized view.', [
                'Open Appointments.',
                'Select the Pending filter.',
                'Review the patient, requested date, meeting type, and status.',
                'Use the information button to read the booking reason when provided.',
                'Select Approve to accept, or Reject to decline the request.',
                'Confirm a rejection when prompted.',
                'Verify that the status changes and the request leaves the actionable pending list.',
            ], 'Approval activates the appointment and adds you to the patient\'s care team without replacing other clinicians.', [
                'Elapsed requests are labeled Expired and cannot be approved or rejected.',
            ]),
            self::section('Reschedule or conclude an appointment', 'Move an active appointment to an available time or record its outcome after the session.', 'appointments', null, [
                'Open Appointments and locate an Approved or Rescheduled appointment.',
                'To move it, select Reschedule, choose a date, load open slots, and select a new time.',
                'Confirm the change and verify the Rescheduled status and effective time.',
                'After a session, select Conclude appointment.',
                'Choose the appropriate outcome, such as completed or no-show, and enter permitted closing information.',
                'Save and verify that the appointment is no longer treated as upcoming.',
            ], 'The patient receives the corresponding status update and dashboard counts use the new state.', [
                'Use the patient-note workflow for detailed clinical documentation.',
            ]),
            self::section('Set or update availability', 'Maintain the schedule patients use when requesting appointments.', 'dashboard', 'Review existing appointments before making a broad availability change.', [
                'Open the clinician Dashboard and find the availability calendar.',
                'Choose the date or recurring availability control.',
                'Add the start and end times during which patients may request appointments.',
                'Save the availability entry.',
                'Review the calendar and confirm the period appears correctly.',
                'Remove or adjust availability when you can no longer accept requests during that period.',
            ], 'Patients see only open slots within saved availability that are not already booked.', [
                'Changing availability does not silently cancel existing approved appointments.',
            ]),
            self::section('Review a patient record and progress', 'Access authorized details, appointments, assessments, moods, goals, and notes.', 'patients', 'Only patients assigned to your caseload are available.', [
                'Open Patients.',
                'Select the patient by name or profile row.',
                'Review their details and recent appointment history.',
                'Select View progress to review attendance, questionnaires, mood trends, and therapy goals.',
                'Use Export record only when an authorized purpose requires a PDF copy.',
                'Return to the patient record to add or review notes and related work.',
            ], 'Only information permitted for the assigned patient is displayed, and record exports are audit logged.', [
                'Verify patient identity before documenting, downloading, or exporting information.',
            ]),
            self::section('Create an assignment for a patient', 'Provide therapeutic work with instructions, an optional due date, and optional worksheet.', 'assignments', 'Prepare the instructions and attachment. Files may be PDF, Office, text, or image formats up to 10 MB.', [
                'Open Assignments and select New Assignment.',
                'Enter a clear title.',
                'Choose the assigned patient.',
                'Optionally choose a due date.',
                'Write instructions explaining what the patient should complete.',
                'Optionally attach a worksheet or supporting file.',
                'Review the patient, due date, instructions, and attachment.',
                'Select Create Assignment and confirm it appears in the list.',
            ], 'The patient receives an alert and can open the assignment, download its worksheet, and submit work.', [
                'Do not include unnecessary sensitive information in filenames or instructions.',
            ]),
            self::section('Review an assignment submission', 'Open submitted work, preview supported files, and mark the work reviewed.', 'assignments', null, [
                'Check the Assignments badge for new submissions.',
                'Open Assignments and select the relevant assignment or submissions view.',
                'Confirm the patient identity and submission time.',
                'Read the response and preview or download the file when present.',
                'Review the content using the appropriate clinical process.',
                'Mark the submission Reviewed.',
                'Confirm that the status and new-submission badge update.',
            ], 'The patient sees Reviewed and can no longer replace that submission.', [
                'Download files only to an approved device and treat them as confidential patient information.',
            ]),
            self::section('Message an assigned patient', 'Communicate within a participant-only conversation for an assigned patient.', 'messages', 'The patient must be assigned to your caseload.', [
                'Open Messages.',
                'Choose an existing conversation from the sidebar.',
                'If needed, use Start a conversation and select an assigned patient.',
                'Confirm the patient name and photo in the header.',
                'Enter the message and select Send once.',
                'Keep the conversation open for realtime replies or use the unread badge later.',
            ], 'The message is stored once, appears immediately, and generates a patient message alert.', [
                'Use private clinician notes for information that should not be shared with the patient.',
            ]),
            self::section('Assign questionnaires, manage goals, and add notes', 'Use the patient record to document and monitor care within established visibility rules.', 'patients', 'Open the correct assigned patient and verify their identity before entering information.', [
                'Open Patients and select the patient.',
                'Use the assessment controls to assign an appropriate PHQ-9 or GAD-7.',
                'After completion, open View progress to review the score, explanation, and trend.',
                'Create or update therapy goals and progress ratings using the goal controls.',
                'To add a note, enter an optional title and the note body.',
                'Select Share with patient only when the note is intended for the patient portal and app.',
                'Save and confirm whether the note is labeled Shared or Private.',
            ], 'Assessments, goals, and notes appear in their appropriate areas with the intended visibility.', [
                'Screening scores support clinical review but are not standalone diagnoses.',
            ]),
        ];
    }

    private static function section(string $title, string $description, string $action, ?string $beforeYouStart, array $steps, string $expectedResult, array $tips = []): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'action' => $action,
            'before_you_start' => $beforeYouStart,
            'steps' => $steps,
            'expected_result' => $expectedResult,
            'tips' => $tips,
        ];
    }
}
