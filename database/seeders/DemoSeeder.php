<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Assignment;
use App\Models\Clinician;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Submission;
use App\Models\User;
use App\Services\JitsiService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: the Dockerfile runs `db:seed` on every container boot.
        // If the demo data already exists, do nothing (avoids unique-email errors).
        if (User::where('email', 'admin@theraconnect.test')->exists()) {
            return;
        }

        // ── Admin ──────────────────────────────────────────
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@theraconnect.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // ── Clinicians ─────────────────────────────────────
        $c1User = User::create([
            'name' => 'Dr. Sarah Chen, MD',
            'email' => 'clinician@theraconnect.test',
            'password' => Hash::make('password'),
            'role' => 'clinician',
        ]);
        $c1 = Clinician::create([
            'user_id' => $c1User->id,
            'license_no' => 'LIC-2024-001',
            'specialization' => 'Cognitive Behavioral Therapy',
            'contact_no' => '555-0100',
        ]);

        $c2User = User::create([
            'name' => 'Dr. James Rivera, PsyD',
            'email' => 'dr.rivera@theraconnect.test',
            'password' => Hash::make('password'),
            'role' => 'clinician',
        ]);
        $c2 = Clinician::create([
            'user_id' => $c2User->id,
            'license_no' => 'LIC-2024-002',
            'specialization' => 'Family Therapy',
            'contact_no' => '555-0101',
        ]);

        // ── Patients ───────────────────────────────────────
        $p1User = User::create([
            'name' => 'Jane Doe',
            'email' => 'patient@theraconnect.test',
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);
        $p1 = Patient::create([
            'user_id' => $p1User->id,
            'assigned_clinician_id' => $c1->id,
            'date_of_birth' => '1995-03-15',
            'contact_no' => '555-0200',
            'address' => '123 Main St, Springfield',
            'emergency_contact' => 'John Doe - 555-0300',
        ]);

        $p2User = User::create([
            'name' => 'Michael Torres',
            'email' => 'michael@theraconnect.test',
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);
        $p2 = Patient::create([
            'user_id' => $p2User->id,
            'assigned_clinician_id' => $c2->id,
            'date_of_birth' => '1988-07-22',
            'contact_no' => '555-0201',
            'address' => '456 Oak Ave, Springfield',
            'emergency_contact' => 'Maria Torres - 555-0301',
        ]);

        $p3User = User::create([
            'name' => 'Emily Watson',
            'email' => 'emily@theraconnect.test',
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);
        $p3 = Patient::create([
            'user_id' => $p3User->id,
            'assigned_clinician_id' => $c1->id,
            'date_of_birth' => '2000-11-08',
            'contact_no' => '555-0202',
            'address' => '789 Pine Rd, Springfield',
            'emergency_contact' => 'David Watson - 555-0302',
        ]);

        // ── Appointments ───────────────────────────────────
        $now = now();

        // Pending — needs clinician action
        Appointment::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'requested_at' => $now->copy()->addDays(2)->setTime(9, 0),
            'mode' => 'in_person', 'status' => 'pending',
            'reason' => 'Anxiety management follow-up',
        ]);
        Appointment::create([
            'patient_id' => $p2->id, 'clinician_id' => $c2->id,
            'requested_at' => $now->copy()->addDays(3)->setTime(10, 0),
            'mode' => 'online', 'status' => 'pending',
            'reason' => 'Family counseling intake',
        ]);
        Appointment::create([
            'patient_id' => $p3->id, 'clinician_id' => $c1->id,
            'requested_at' => $now->copy()->addDays(1)->setTime(14, 0),
            'mode' => 'in_person', 'status' => 'pending',
            'reason' => 'Initial consultation — anxiety screening',
        ]);

        // Approved — confirmed upcoming
        Appointment::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'requested_at' => $now->copy()->addDays(5)->setTime(9, 0),
            'scheduled_at' => $now->copy()->addDays(5)->setTime(9, 0),
            'mode' => 'in_person', 'status' => 'approved',
            'reason' => 'Weekly CBT session',
            'clinic_notes' => 'Patient responding well. Continue exposure exercises.',
        ]);
        Appointment::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'requested_at' => $now->copy()->subDays(7)->setTime(9, 0),
            'scheduled_at' => $now->copy()->subDays(7)->setTime(9, 0),
            'mode' => 'in_person', 'status' => 'completed',
            'reason' => 'Weekly CBT session',
            'clinic_notes' => 'Discussed sleep hygiene. Assigned sleep journal.',
        ]);
        Appointment::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'requested_at' => $now->copy()->subDays(14)->setTime(9, 0),
            'scheduled_at' => $now->copy()->subDays(14)->setTime(9, 0),
            'mode' => 'in_person', 'status' => 'approved',
            'reason' => 'Initial intake session',
        ]);
        Appointment::create([
            'patient_id' => $p2->id, 'clinician_id' => $c2->id,
            'requested_at' => $now->copy()->subDays(3)->setTime(11, 0),
            'scheduled_at' => $now->copy()->subDays(3)->setTime(11, 0),
            'mode' => 'online', 'status' => 'completed',
            'reason' => 'Couples session',
            'clinic_notes' => 'Good progress on communication goals.',
        ]);

        // Approved online — has a live Jitsi room (Join button shows out of the box)
        $onlineApproved = Appointment::create([
            'patient_id' => $p2->id, 'clinician_id' => $c2->id,
            'requested_at' => $now->copy()->addDays(4)->setTime(15, 0),
            'scheduled_at' => $now->copy()->addDays(4)->setTime(15, 0),
            'mode' => 'online', 'status' => 'approved',
            'reason' => 'Follow-up video consultation',
        ]);
        $onlineApproved->update([
            'meeting_link' => app(JitsiService::class)->generateMeetingLink($onlineApproved->id),
        ]);

        // Rejected — was denied
        Appointment::create([
            'patient_id' => $p2->id, 'clinician_id' => $c1->id,
            'requested_at' => $now->copy()->addDays(8)->setTime(16, 0),
            'mode' => 'in_person', 'status' => 'rejected',
            'reason' => 'Requested outside clinic hours',
        ]);

        // Cancelled — patient cancelled
        Appointment::create([
            'patient_id' => $p3->id, 'clinician_id' => $c1->id,
            'requested_at' => $now->copy()->subDays(5)->setTime(13, 0),
            'mode' => 'in_person', 'status' => 'cancelled',
            'reason' => 'Scheduling conflict',
        ]);

        // ── Assignments ─────────────────────────────────────
        $a1 = Assignment::create([
            'clinician_id' => $c1->id, 'patient_id' => $p1->id,
            'title' => 'Daily Mood Journal',
            'description' => 'Record your mood on a scale of 1-10 each morning and evening. Note any triggers. Complete for 7 consecutive days.',
            'due_date' => $now->copy()->addDays(5),
        ]);
        $a2 = Assignment::create([
            'clinician_id' => $c1->id, 'patient_id' => $p1->id,
            'title' => 'Breathing Exercise Practice',
            'description' => 'Practice the 4-7-8 breathing technique twice daily (morning and evening). Time yourself — aim for 5 minutes per session. Note how you feel before and after.',
            'due_date' => $now->copy()->addDays(3),
        ]);
        $a3 = Assignment::create([
            'clinician_id' => $c2->id, 'patient_id' => $p2->id,
            'title' => 'Communication Reflection',
            'description' => 'Write a 300-word reflection on last week\'s family conversation. What went well? What would you change? What patterns do you notice?',
            'due_date' => $now->copy()->addDays(7),
        ]);
        $a4 = Assignment::create([
            'clinician_id' => $c1->id, 'patient_id' => $p3->id,
            'title' => 'Anxiety Trigger Log',
            'description' => 'For the next 3 days, log every time you feel anxious. Note: time, situation, physical symptoms, and what helped calm you down.',
            'due_date' => $now->copy()->addDays(3),
        ]);

        // ── Submissions ────────────────────────────────────
        Submission::create([
            'assignment_id' => $a2->id, 'patient_id' => $p1->id,
            'content' => 'Day 1: Morning — felt tense before breathing (4/10 after → 7/10). Evening — much calmer, fell asleep faster. Day 2: Morning — noticed jaw tension reduced. Technique is getting easier to remember.',
            'status' => 'submitted',
            'submitted_at' => $now->copy()->subDays(1),
        ]);
        Submission::create([
            'assignment_id' => $a1->id, 'patient_id' => $p1->id,
            'content' => 'Mon: 4 (work stress), 6 after walk. Tue: 3 (argument), 5 after journaling. Wed: 5, 7 after yoga. Thu: 6, 8. Fri: 4, 6 after calling friend. Sat: 7, 8. Sun: 8, 9.',
            'status' => 'reviewed',
            'submitted_at' => $now->copy()->subDays(6),
            'reviewed_at' => $now->copy()->subDays(5),
        ]);

        // ── Notifications ──────────────────────────────────
        Notification::create([
            'user_id' => $p1User->id,
            'type' => 'appointment_approved',
            'title' => 'Appointment Approved',
            'body' => 'Your appointment on ' . $now->copy()->addDays(5)->format('M j') . ' at 9:00 AM is confirmed.',
            'data' => json_encode(['appointment_id' => 4]),
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subHours(3),
        ]);
        Notification::create([
            'user_id' => $p1User->id,
            'type' => 'assignment_created',
            'title' => 'New Assignment',
            'body' => 'Dr. Chen assigned you: Daily Mood Journal.',
            'data' => json_encode(['assignment_id' => $a1->id]),
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subDays(7),
            'read_at' => $now->copy()->subDays(6),
        ]);
        Notification::create([
            'user_id' => $p2User->id,
            'type' => 'appointment_rejected',
            'title' => 'Appointment Update',
            'body' => 'Your requested appointment was not approved. Please rebook or contact the clinic.',
            'data' => json_encode(['appointment_id' => 8]),
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subDays(2),
        ]);
        Notification::create([
            'user_id' => $p3User->id,
            'type' => 'assignment_deadline',
            'title' => 'Assignment Due Soon',
            'body' => 'Anxiety Trigger Log is due in 24 hours.',
            'data' => json_encode(['assignment_id' => $a4->id]),
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subHours(12),
        ]);
        Notification::create([
            'user_id' => $p1User->id,
            'type' => 'appointment_reminder',
            'title' => 'Appointment Reminder',
            'body' => 'Reminder: you have an appointment tomorrow at 9:00 AM with Dr. Chen.',
            'data' => json_encode(['appointment_id' => 4]),
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subHours(20),
            'read_at' => $now->copy()->subHours(19),
        ]);

        // ── Chatbot intents ─────────────────────────────────
        $this->call(ChatbotSeeder::class);
    }
}
