<?php

namespace App\Support;

final class UserGuide
{
    public const VERSION = '2.1';

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
            self::section('Book an appointment', 'Choose who you want to meet, when you want to meet, and whether the appointment will be online or in person.', 'appointments', 'Before booking, check that the phone number and other details in your profile are correct.', [
                'Open Appointments from the menu.',
                'Select Book Appointment.',
                'Choose a clinician. Their area of care is shown below their name when available.',
                'Choose a date to see the times when the clinician is available.',
                'Choose an available time. If no times are shown, try another date.',
                'Choose In person or Online (video call).',
                'You may add a short reason for the visit. Only share what you are comfortable sharing.',
                'Check that the details are correct, then select Confirm booking once.',
                'Go back to My Appointments. Your request should be marked Pending.',
            ], 'Your request will stay Pending until the clinician approves it, rejects it, or suggests a new schedule.', [
                'A time may become unavailable if another patient books it first. If this happens, choose another time.',
                'For an approved online appointment, the video-call link will appear in the appointment details when it is ready.',
            ]),
            self::section('View or cancel an appointment', 'See your appointment details, check for schedule changes, or cancel an appointment when allowed.', 'appointments', null, [
                'Open Appointments.',
                'Use the Status, Appointment type, or Date filters if you need help finding it, then select Apply.',
                'Select the appointment or choose View details.',
                'Check the date, time, clinician, appointment type, status, reason, and any note from the clinician.',
                'For an approved online appointment, select Join Video Call when the button becomes available.',
                'If the appointment can still be cancelled, select Cancel appointment.',
                'Confirm your choice. The appointment should now be marked Cancelled.',
            ], 'The cancelled appointment will stay in your appointment history, but it is no longer active.', [
                'Expired, rejected, completed, and already cancelled appointments cannot be cancelled again.',
                'Contact the clinic if you need to make a late change that the system no longer allows.',
            ]),
            self::section('Submit or update an assignment', 'Read the instructions and send your answer as text, a file, or both.', 'assignments', 'Prepare your answer or file first. You can upload a PDF, Word document, text file, or image up to 10 MB.', [
                'Open Assignments.',
                'Select an assignment marked To do.',
                'Read the title, clinician, due date, and full instructions.',
                'If a worksheet is attached, select Download worksheet and complete it.',
                'Under Your Submission, type your answer, choose a file, or do both.',
                'Make sure you selected the correct file and that it is no larger than 10 MB.',
                'Select Submit and wait for your answer to appear on the page.',
                'Return to Assignments and confirm that the status is Submitted.',
                'If you need to correct it before your clinician reviews it, reopen the assignment and select Update submission.',
            ], 'The assignment will be marked Submitted. After your clinician marks it Reviewed, you can no longer change it.', [
                'Do not upload unrelated personal or clinical documents.',
                'Keep a local copy of important work before uploading it.',
            ]),
            self::section('Send a message to your clinician', 'Send a private message to a clinician who is part of your care team.', 'messages', 'You can only message clinicians who are already part of your care team.', [
                'Open Messages.',
                'Choose the clinician from the conversation list.',
                'Check the name at the top to make sure you opened the correct conversation.',
                'Type your message in the box at the bottom.',
                'Select Send once and wait for the message to appear.',
                'New replies should appear automatically while the conversation is open.',
                'If you see an offline message, reconnect to the internet and select Try again.',
            ], 'Your message will appear in the conversation, and the clinician will be notified.', [
                'Messaging is not an emergency service. Use emergency or crisis services for immediate danger.',
                'Do not send passwords, financial information, or unrelated sensitive documents.',
            ]),
            self::section('Complete your daily mood check-in', 'Rate how you feel today and look back at your recent check-ins.', 'progress', 'You can submit one mood check-in each day. You cannot change it after saving, so review it first.', [
                'Open Progress, then Mood Check-ins.',
                'Move the slider from 1 (Very low) to 10 (Great).',
                'You may add a short note about what affected your mood today.',
                'Review the score and note before saving.',
                'Select Save today\'s check-in once.',
                'Confirm that Today\'s check-in is complete appears with the selected score.',
                'Look at Recent check-ins to review your earlier entries.',
            ], 'Your mood check-in will be saved for today based on Philippine time.', [
                'Mood check-ins can help you notice patterns, but they do not provide a diagnosis.',
                'Talk to your clinician if your mood gets worse or you notice a worrying pattern.',
            ]),
            self::section('Complete a PHQ-9 or GAD-7 questionnaire', 'Answer questions about how you have been feeling and review your result.', 'progress', 'Complete the questionnaire in a private place. Answer based on the time period shown in the instructions.', [
                'Open Progress or Questionnaires.',
                'Select an item marked To complete.',
                'Read the instructions and the time period the questions ask about.',
                'Select one response for every required question.',
                'Review the answers and submit the questionnaire.',
                'After submitting, read the score and the explanation shown with it.',
                'Talk about the result with your clinician, especially if your symptoms affect your daily life.',
            ], 'The questionnaire will be marked Done, and the result will appear in your progress record.', [
                'PHQ-9 and GAD-7 help track symptoms, but the result alone is not a diagnosis.',
                'Seek immediate help if you may harm yourself or another person; do not wait for a portal reply.',
            ]),
            self::section('Check your notifications', 'See new updates about appointments, assignments, questionnaires, and messages.', 'notifications', null, [
                'Check the number beside the bell icon to see if you have unread updates.',
                'Open Notifications to read updates about your appointments, assignments, questionnaires, and account.',
                'Mark one update as read, or select Mark all as read to clear all unread updates.',
                'Check the number beside Messages separately for unread messages.',
                'Open the related appointment, assignment, or conversation for the latest details.',
            ], 'After you read an update, it will no longer be included in the unread number. The update itself will not be deleted.', [
                'To receive alerts on an Android phone, allow notifications and keep the phone connected to the internet.',
            ]),
            self::section('Update your profile, photo, or password', 'Keep your personal details up to date and your account secure.', 'profile', 'To change your photo, prepare a clear JPG or PNG image no larger than 2 MB.', [
                'Open your profile from the picture or name in the top navigation.',
                'Select Edit Profile and change the details you need to update.',
                'To change your photo, choose an image, adjust which part will be shown, and apply the crop.',
                'Save your changes and check that the new information appears.',
                'To change the password, open Change Password.',
                'Enter your current password. Then enter the new password twice and follow the password rules shown on the page.',
                'Save the password change and use the new password on the next login.',
            ], 'Your saved details and profile photo will appear anywhere your profile is shown in TheraConnect.', [
                'Never share your password. Sign out when using a shared device.',
            ]),
        ];
    }

    private static function clinicianSections(): array
    {
        return [
            self::section('Review an appointment request', 'Check a patient\'s request, then approve or reject it.', 'appointments', 'Make sure you have opened the correct patient and that the requested schedule works for you.', [
                'Open Appointments.',
                'Select the Pending filter.',
                'Check the patient, requested date and time, appointment type, and status.',
                'Select the information button to read the patient\'s reason, if one was provided.',
                'Select Approve to accept the request, or Reject to decline it.',
                'Confirm a rejection when prompted.',
                'Check that the status changed and that the request is no longer in the Pending list.',
            ], 'When you approve the request, the appointment becomes active and you are added to the patient\'s care team. Any other assigned clinicians will remain assigned.', [
                'Requests for dates and times that have already passed are marked Expired and can no longer be approved or rejected.',
            ]),
            self::section('Reschedule or finish an appointment', 'Change an appointment schedule or record what happened after the session.', 'appointments', null, [
                'Open Appointments and find an appointment marked Approved or Rescheduled.',
                'To change the schedule, select Reschedule, choose a date, and then choose an available time.',
                'Confirm the change. Check that the appointment is marked Rescheduled and shows the new date and time.',
                'After a session, select Conclude appointment.',
                'Choose what happened, such as Completed or No-show, and add any information requested on the form.',
                'Save the result and check that the appointment no longer appears as upcoming.',
            ], 'The patient will be notified, and the dashboard will show the updated appointment status.', [
                'Use the patient notes area for detailed clinical notes.',
            ]),
            self::section('Set or update your available times', 'Choose the dates and times patients can request appointments with you.', 'dashboard', 'Check your existing appointments before changing a large part of your schedule.', [
                'Open the Dashboard and find the availability calendar.',
                'Choose the date or the option for a repeating schedule.',
                'Enter the start and end times when you can accept appointments.',
                'Save the schedule.',
                'Check the calendar to make sure the date and times are correct.',
                'Change or remove the schedule if you are no longer available at those times.',
            ], 'Patients will only see times you made available that have not already been booked.', [
                'Changing your availability does not cancel appointments that were already approved.',
            ]),
            self::section('View a patient\'s record and progress', 'Review the patient\'s details, appointments, questionnaires, mood check-ins, goals, and notes.', 'patients', 'You can only open records for patients assigned to you.', [
                'Open Patients.',
                'Select the patient\'s name or profile.',
                'Review their details and recent appointment history.',
                'Select View progress to review attendance, questionnaires, mood trends, and therapy goals.',
                'Select Export record only when you need an approved PDF copy of the patient\'s record.',
                'Return to the patient record when you need to add or review notes and other care information.',
            ], 'The record will show the information you are allowed to view for that assigned patient. The system keeps a record whenever a patient file is exported.', [
                'Always check the patient\'s identity before adding notes, downloading files, or exporting a record.',
            ]),
            self::section('Create an assignment for a patient', 'Give a patient a task with instructions, an optional due date, and an optional worksheet.', 'assignments', 'Prepare the instructions and any file you want to attach. The file can be a PDF, Office document, text file, or image up to 10 MB.', [
                'Open Assignments and select New Assignment.',
                'Enter a clear title.',
                'Choose the assigned patient.',
                'Add a due date if needed.',
                'Write instructions explaining what the patient should complete.',
                'Attach a worksheet or supporting file if needed.',
                'Review the patient, due date, instructions, and attachment.',
                'Select Create Assignment and confirm it appears in the list.',
            ], 'The patient receives an alert and can open the assignment, download its worksheet, and submit work.', [
                'Do not include unnecessary sensitive information in filenames or instructions.',
            ]),
            self::section('Review a patient\'s assignment', 'Read the patient\'s answer, open any attached file, and mark the work as reviewed.', 'assignments', null, [
                'Check the Assignments badge for new submissions.',
                'Open Assignments and choose the assignment with a new submission.',
                'Check the patient\'s name and the time the work was submitted.',
                'Read the answer and open or download the attached file, if there is one.',
                'Review the patient\'s work.',
                'Mark the submission Reviewed.',
                'Check that the status changes and the new-submission number is updated.',
            ], 'The patient will see that the assignment is Reviewed and will no longer be able to replace the submission.', [
                'Only download patient files to an approved device. Treat every file as private patient information.',
            ]),
            self::section('Send a message to an assigned patient', 'Send a private message to a patient who is assigned to you.', 'messages', 'You can only start a conversation with a patient who is assigned to you.', [
                'Open Messages.',
                'Choose an existing conversation from the sidebar.',
                'If needed, use Start a conversation and select an assigned patient.',
                'Confirm the patient name and photo in the header.',
                'Enter the message and select Send once.',
                'New replies should appear automatically while the conversation is open. You can also check the unread-message number later.',
            ], 'Your message will appear in the conversation, and the patient will be notified.', [
                'Use private clinician notes for information that should not be shared with the patient.',
            ]),
            self::section('Assign questionnaires, manage goals, and add notes', 'Use the patient\'s record to track care and choose which notes the patient can see.', 'patients', 'Open the correct patient and check their identity before entering any information.', [
                'Open Patients and select the patient.',
                'Choose the option to assign a PHQ-9 or GAD-7 questionnaire when appropriate.',
                'After the patient completes it, open View progress to see the score, explanation, and changes over time.',
                'Use the Goals area to create a goal or update its progress.',
                'To add a note, enter a title if needed, then write the note.',
                'Select Share with patient only when the note is intended for the patient portal and app.',
                'Save and confirm whether the note is labeled Shared or Private.',
            ], 'The questionnaire, goals, and notes will appear in the correct areas. Shared notes will be visible to the patient; private notes will not.', [
                'Questionnaire scores can support your review, but the score alone is not a diagnosis.',
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
