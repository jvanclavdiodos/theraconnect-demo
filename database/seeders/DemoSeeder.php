<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Clinician;
use App\Models\GoalRating;
use App\Models\MoodLog;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Submission;
use App\Models\TherapyGoal;
use App\Models\User;
use App\Services\JitsiService;
use App\Support\Assessments;
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
        User::create([
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
        // Dr. Chen — Jane Doe (primary demo patient, lots of history)
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

        // Dr. Rivera — Michael Torres
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

        // Dr. Chen — Emily Watson
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

        // Dr. Rivera — Sophia Nguyen (second patient, newer to therapy)
        $p4User = User::create([
            'name' => 'Sophia Nguyen',
            'email' => 'sophia@theraconnect.test',
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);
        $p4 = Patient::create([
            'user_id' => $p4User->id,
            'assigned_clinician_id' => $c2->id,
            'date_of_birth' => '1992-04-12',
            'contact_no' => '555-0203',
            'address' => '32 Maple Lane, Springfield',
            'emergency_contact' => 'Linh Nguyen - 555-0303',
        ]);

        // ── Pending clinician request ──────────────────────
        // Olivia self-registered and asked to join Dr. Chen's caseload; she is
        // unassigned until Dr. Chen approves (shows in "Pending requests").
        $p5User = User::create([
            'name' => 'Olivia Reyes',
            'email' => 'olivia@theraconnect.test',
            'password' => Hash::make('password'),
            'role' => 'patient',
        ]);
        Patient::create([
            'user_id' => $p5User->id,
            'requested_clinician_id' => $c1->id,
            'clinician_request_status' => Patient::REQUEST_PENDING,
            'contact_no' => '555-0204',
            'gender' => 'Female',
            'personal_issues' => 'Looking for help managing anxiety and stress.',
        ]);
        Notification::create([
            'user_id' => $c1User->id,
            'type' => 'patient_request',
            'title' => 'New patient request',
            'body' => 'Olivia Reyes requested to be added to your caseload. Review and approve or decline.',
            'data' => null,
            'channel' => 'fcm',
            'sent_at' => now()->subHours(2),
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
        Appointment::create([
            'patient_id' => $p4->id, 'clinician_id' => $c2->id,
            'requested_at' => $now->copy()->addDays(4)->setTime(11, 0),
            'mode' => 'online', 'status' => 'pending',
            'reason' => 'Session 3 check-in',
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

        // Completed sessions — Jane (attendance history)
        $apptJ1 = Appointment::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'requested_at' => $now->copy()->subDays(7)->setTime(9, 0),
            'scheduled_at' => $now->copy()->subDays(7)->setTime(9, 0),
            'mode' => 'in_person', 'status' => 'completed',
            'reason' => 'Weekly CBT session',
            'clinic_notes' => 'Discussed sleep hygiene. Assigned sleep journal.',
        ]);
        $apptJ2 = Appointment::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'requested_at' => $now->copy()->subDays(14)->setTime(9, 0),
            'scheduled_at' => $now->copy()->subDays(14)->setTime(9, 0),
            'mode' => 'in_person', 'status' => 'completed',
            'reason' => 'Initial intake session',
            'clinic_notes' => 'Completed PHQ-9. Discussed treatment goals.',
        ]);
        Appointment::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'requested_at' => $now->copy()->subDays(21)->setTime(9, 0),
            'scheduled_at' => $now->copy()->subDays(21)->setTime(9, 0),
            'mode' => 'in_person', 'status' => 'completed',
            'reason' => 'Consultation',
            'clinic_notes' => 'Welcomed to the clinic. Goals discussed.',
        ]);

        // Completed sessions — Michael
        $apptM1 = Appointment::create([
            'patient_id' => $p2->id, 'clinician_id' => $c2->id,
            'requested_at' => $now->copy()->subDays(3)->setTime(11, 0),
            'scheduled_at' => $now->copy()->subDays(3)->setTime(11, 0),
            'mode' => 'online', 'status' => 'completed',
            'reason' => 'Couples session',
            'clinic_notes' => 'Good progress on communication goals.',
        ]);
        $apptM2 = Appointment::create([
            'patient_id' => $p2->id, 'clinician_id' => $c2->id,
            'requested_at' => $now->copy()->subDays(17)->setTime(11, 0),
            'scheduled_at' => $now->copy()->subDays(17)->setTime(11, 0),
            'mode' => 'online', 'status' => 'completed',
            'reason' => 'Initial family session',
            'clinic_notes' => 'Opened up about communication issues at home.',
        ]);

        // Completed sessions — Sophia
        $apptS1 = Appointment::create([
            'patient_id' => $p4->id, 'clinician_id' => $c2->id,
            'requested_at' => $now->copy()->subDays(10)->setTime(10, 0),
            'scheduled_at' => $now->copy()->subDays(10)->setTime(10, 0),
            'mode' => 'online', 'status' => 'completed',
            'reason' => 'Session 2',
            'clinic_notes' => 'Began exploring workplace anxiety patterns.',
        ]);
        Appointment::create([
            'patient_id' => $p4->id, 'clinician_id' => $c2->id,
            'requested_at' => $now->copy()->subDays(24)->setTime(10, 0),
            'scheduled_at' => $now->copy()->subDays(24)->setTime(10, 0),
            'mode' => 'online', 'status' => 'completed',
            'reason' => 'Initial intake',
            'clinic_notes' => 'Presenting concerns: work stress, difficulty sleeping.',
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
        Assignment::create([
            'clinician_id' => $c2->id, 'patient_id' => $p4->id,
            'title' => 'Sleep & Stress Log',
            'description' => 'Each morning this week, note how many hours you slept, your stress level (1–10), and one thing that felt manageable yesterday. We\'ll review together next session.',
            'due_date' => $now->copy()->addDays(6),
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

        // ── Assessments (PHQ-9 / GAD-7) ────────────────────
        // Jane: two completed PHQ-9s showing clear improvement, plus a pending GAD-7
        $phq9Items = Assessments::definition('phq9')['items'];
        $gad7Items = Assessments::definition('gad7')['items'];

        // Jane PHQ-9 #1 — 3 weeks ago, score 14 (Moderate)
        $r1 = [2, 2, 1, 2, 2, 1, 1, 2, 1]; // sum=14
        Assessment::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'instrument' => 'phq9', 'status' => 'completed',
            'responses' => $r1, 'score' => array_sum($r1),
            'completed_at' => $now->copy()->subDays(21),
        ]);

        // Jane PHQ-9 #2 — 1 week ago, score 8 (Mild) — showing improvement
        $r2 = [1, 1, 1, 1, 1, 1, 1, 0, 1]; // sum=8
        Assessment::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'instrument' => 'phq9', 'status' => 'completed',
            'responses' => $r2, 'score' => array_sum($r2),
            'completed_at' => $now->copy()->subDays(7),
        ]);

        // Jane GAD-7 — pending (just assigned)
        Assessment::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'instrument' => 'gad7', 'status' => 'pending',
        ]);

        // Emily PHQ-9 — score 18 (Moderately severe), flagged for closer monitoring
        $r3 = [3, 2, 2, 2, 2, 2, 2, 2, 1]; // sum=18
        Assessment::create([
            'patient_id' => $p3->id, 'clinician_id' => $c1->id,
            'instrument' => 'phq9', 'status' => 'completed',
            'responses' => $r3, 'score' => array_sum($r3),
            'completed_at' => $now->copy()->subDays(3),
        ]);

        // Michael GAD-7 #1 — 17 days ago, score 12 (Moderate)
        $r4 = [2, 2, 2, 2, 2, 1, 1]; // sum=12
        Assessment::create([
            'patient_id' => $p2->id, 'clinician_id' => $c2->id,
            'instrument' => 'gad7', 'status' => 'completed',
            'responses' => $r4, 'score' => array_sum($r4),
            'completed_at' => $now->copy()->subDays(17),
        ]);

        // Michael GAD-7 #2 — 3 days ago, score 7 (Mild) — improving
        $r5 = [1, 1, 1, 1, 1, 1, 1]; // sum=7
        Assessment::create([
            'patient_id' => $p2->id, 'clinician_id' => $c2->id,
            'instrument' => 'gad7', 'status' => 'completed',
            'responses' => $r5, 'score' => array_sum($r5),
            'completed_at' => $now->copy()->subDays(3),
        ]);

        // Sophia PHQ-9 — pending (just assigned at intake)
        Assessment::create([
            'patient_id' => $p4->id, 'clinician_id' => $c2->id,
            'instrument' => 'phq9', 'status' => 'pending',
        ]);

        // ── Mood logs ──────────────────────────────────────
        // Jane: 14-day trend (recovering, low-to-mid scores rising)
        $janeMoods = [
            [-13, 4, 'Rough morning'],
            [-12, 5, null],
            [-11, 4, 'Stressed about work'],
            [-10, 5, null],
            [-9, 6, 'Breathing exercise helped'],
            [-8, 5, null],
            [-7, 7, 'Good session with Dr. Chen'],
            [-6, 6, null],
            [-5, 6, 'Slept better'],
            [-4, 7, null],
            [-3, 7, 'Feeling more in control'],
            [-2, 8, null],
            [-1, 7, 'Long day but managed'],
            [0, 8, 'Best week in a while'],
        ];
        foreach ($janeMoods as [$daysAgo, $score, $note]) {
            MoodLog::create([
                'patient_id' => $p1->id,
                'score' => $score,
                'note' => $note,
                'created_at' => $now->copy()->addDays($daysAgo)->setTime(8, 30),
                'updated_at' => $now->copy()->addDays($daysAgo)->setTime(8, 30),
            ]);
        }

        // Emily: 7-day trend (consistently low, clinician awareness)
        $emilyMoods = [
            [-6, 3, 'Hard day'],
            [-5, 4, null],
            [-4, 3, 'Couldn\'t sleep'],
            [-3, 4, 'Anxious about everything'],
            [-2, 5, null],
            [-1, 4, null],
            [0, 5, 'Slightly better today'],
        ];
        foreach ($emilyMoods as [$daysAgo, $score, $note]) {
            MoodLog::create([
                'patient_id' => $p3->id,
                'score' => $score,
                'note' => $note,
                'created_at' => $now->copy()->addDays($daysAgo)->setTime(9, 0),
                'updated_at' => $now->copy()->addDays($daysAgo)->setTime(9, 0),
            ]);
        }

        // Michael: improving mood trend mirroring GAD-7 improvement
        $michaelMoods = [
            [-16, 4, 'Tense at home'],
            [-12, 5, null],
            [-9, 5, 'Tried the communication exercise'],
            [-6, 6, 'Had a calm conversation with partner'],
            [-3, 7, 'Slept well for first time in weeks'],
            [0, 7, 'Feeling heard'],
        ];
        foreach ($michaelMoods as [$daysAgo, $score, $note]) {
            MoodLog::create([
                'patient_id' => $p2->id,
                'score' => $score,
                'note' => $note,
                'created_at' => $now->copy()->addDays($daysAgo)->setTime(20, 0),
                'updated_at' => $now->copy()->addDays($daysAgo)->setTime(20, 0),
            ]);
        }

        // Sophia: early engagement, still volatile
        $sophiaMoods = [
            [-9, 4, 'Overwhelmed at work'],
            [-4, 5, 'Good session helped'],
            [0, 6, 'Starting to feel less alone with this'],
        ];
        foreach ($sophiaMoods as [$daysAgo, $score, $note]) {
            MoodLog::create([
                'patient_id' => $p4->id,
                'score' => $score,
                'note' => $note,
                'created_at' => $now->copy()->addDays($daysAgo)->setTime(21, 0),
                'updated_at' => $now->copy()->addDays($daysAgo)->setTime(21, 0),
            ]);
        }

        // ── Therapy goals (GAS) ────────────────────────────
        // Jane: two active goals with improving GAS ratings
        $gj1 = TherapyGoal::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'description' => 'Attend a social gathering without leaving early due to anxiety.',
            'status' => 'active',
            'target_date' => $now->copy()->addDays(30)->toDateString(),
            'created_at' => $now->copy()->subDays(21),
        ]);
        GoalRating::create([
            'therapy_goal_id' => $gj1->id,
            'appointment_id' => $apptJ2->id,
            'rating' => -1,
            'note' => 'Left the party after 30 min — better than before but goal not yet met.',
            'created_at' => $now->copy()->subDays(14),
        ]);
        GoalRating::create([
            'therapy_goal_id' => $gj1->id,
            'appointment_id' => $apptJ1->id,
            'rating' => 0,
            'note' => 'Stayed for full 90 minutes. Felt uncomfortable but managed.',
            'created_at' => $now->copy()->subDays(7),
        ]);

        $gj2 = TherapyGoal::create([
            'patient_id' => $p1->id, 'clinician_id' => $c1->id,
            'description' => 'Use a coping technique (breathing or grounding) at least once per day.',
            'status' => 'met',
            'created_at' => $now->copy()->subDays(14),
        ]);
        GoalRating::create([
            'therapy_goal_id' => $gj2->id,
            'appointment_id' => $apptJ1->id,
            'rating' => 2,
            'note' => 'Patient reporting daily use for 10 consecutive days.',
            'created_at' => $now->copy()->subDays(7),
        ]);

        // Emily: one goal, not rated yet (new)
        TherapyGoal::create([
            'patient_id' => $p3->id, 'clinician_id' => $c1->id,
            'description' => 'Establish a consistent sleep routine — in bed by midnight, no screens 30 min before.',
            'status' => 'active',
            'target_date' => $now->copy()->addDays(21)->toDateString(),
            'created_at' => $now->copy()->subDays(3),
        ]);

        // Michael: one goal met (communication), one still active
        $gm1 = TherapyGoal::create([
            'patient_id' => $p2->id, 'clinician_id' => $c2->id,
            'description' => 'Practice active listening during family discussions without interrupting.',
            'status' => 'met',
            'created_at' => $now->copy()->subDays(17),
        ]);
        GoalRating::create([
            'therapy_goal_id' => $gm1->id,
            'appointment_id' => $apptM2->id,
            'rating' => -1,
            'note' => 'Interrupted twice but caught himself. Awareness is improving.',
            'created_at' => $now->copy()->subDays(17),
        ]);
        GoalRating::create([
            'therapy_goal_id' => $gm1->id,
            'appointment_id' => $apptM1->id,
            'rating' => 1,
            'note' => 'Partner reported noticeable change. Full conversation without interruption.',
            'created_at' => $now->copy()->subDays(3),
        ]);

        $gm2 = TherapyGoal::create([
            'patient_id' => $p2->id, 'clinician_id' => $c2->id,
            'description' => 'Schedule one intentional activity with the family per week (dinner, walk, or game night).',
            'status' => 'active',
            'target_date' => $now->copy()->addDays(28)->toDateString(),
            'created_at' => $now->copy()->subDays(3),
        ]);
        GoalRating::create([
            'therapy_goal_id' => $gm2->id,
            'appointment_id' => $apptM1->id,
            'rating' => 0,
            'note' => 'Did one family walk — first time in months. Expected level of effort.',
            'created_at' => $now->copy()->subDays(3),
        ]);

        // Sophia: one active goal, rated once after session 2
        $gs1 = TherapyGoal::create([
            'patient_id' => $p4->id, 'clinician_id' => $c2->id,
            'description' => 'Identify and name one source of workplace stress each day without dwelling on it.',
            'status' => 'active',
            'target_date' => $now->copy()->addDays(14)->toDateString(),
            'created_at' => $now->copy()->subDays(10),
        ]);
        GoalRating::create([
            'therapy_goal_id' => $gs1->id,
            'appointment_id' => $apptS1->id,
            'rating' => -1,
            'note' => 'Still ruminating for extended periods, but can name the stressor now.',
            'created_at' => $now->copy()->subDays(10),
        ]);

        // ── Notifications ──────────────────────────────────
        Notification::create([
            'user_id' => $p1User->id,
            'type' => 'appointment_approved',
            'title' => 'Appointment Approved',
            'body' => 'Your appointment on '.$now->copy()->addDays(5)->format('M j').' at 9:00 AM is confirmed.',
            'data' => ['appointment_id' => 5],
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subHours(3),
        ]);
        Notification::create([
            'user_id' => $p1User->id,
            'type' => 'assignment_created',
            'title' => 'New Assignment',
            'body' => 'Dr. Chen assigned you: Daily Mood Journal.',
            'data' => ['assignment_id' => $a1->id],
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subDays(7),
            'read_at' => $now->copy()->subDays(6),
        ]);
        Notification::create([
            'user_id' => $p1User->id,
            'type' => 'assessment_assigned',
            'title' => 'New Questionnaire',
            'body' => 'Dr. Chen has assigned you a GAD-7 (Anxiety) questionnaire. Please complete it when you can.',
            'data' => null,
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subHours(6),
        ]);
        Notification::create([
            'user_id' => $p2User->id,
            'type' => 'appointment_rejected',
            'title' => 'Appointment Update',
            'body' => 'Your requested appointment was not approved. Please rebook or contact the clinic.',
            'data' => ['appointment_id' => 11],
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subDays(2),
        ]);
        Notification::create([
            'user_id' => $p3User->id,
            'type' => 'assignment_deadline',
            'title' => 'Assignment Due Soon',
            'body' => 'Anxiety Trigger Log is due in 24 hours.',
            'data' => ['assignment_id' => $a4->id],
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subHours(12),
        ]);
        Notification::create([
            'user_id' => $p1User->id,
            'type' => 'appointment_reminder',
            'title' => 'Appointment Reminder',
            'body' => 'Reminder: you have an appointment tomorrow at 9:00 AM with Dr. Chen.',
            'data' => ['appointment_id' => 5],
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subHours(20),
            'read_at' => $now->copy()->subHours(19),
        ]);
        Notification::create([
            'user_id' => $p4User->id,
            'type' => 'assessment_assigned',
            'title' => 'New Questionnaire',
            'body' => 'Dr. Rivera has assigned you a PHQ-9 (Depression) questionnaire. Please complete it when you can.',
            'data' => null,
            'channel' => 'fcm',
            'sent_at' => $now->copy()->subDays(1),
        ]);

        // ── Chatbot intents ─────────────────────────────────
        $this->call(ChatbotSeeder::class);
    }
}
