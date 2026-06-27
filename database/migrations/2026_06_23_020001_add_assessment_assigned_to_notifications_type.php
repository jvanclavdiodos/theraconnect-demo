<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'assessment_assigned' to the notifications.type enum so a patient is
     * notified when their clinician asks them to complete a questionnaire
     * (PHQ-9 / GAD-7).
     *
     * MySQL only (real ENUM column needs an in-place ALTER on existing DBs).
     * SQLite (tests) gets the value from the edited create migration on
     * migrate:fresh, so this is a no-op there.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE notifications MODIFY COLUMN type ENUM('.
            "'appointment_requested','appointment_approved','appointment_rejected',".
            "'appointment_rescheduled','appointment_reminder','assignment_created',".
            "'assignment_deadline','message_received','assessment_assigned','generic') NOT NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE notifications MODIFY COLUMN type ENUM('.
            "'appointment_requested','appointment_approved','appointment_rejected',".
            "'appointment_rescheduled','appointment_reminder','assignment_created',".
            "'assignment_deadline','message_received','generic') NOT NULL"
        );
    }
};
