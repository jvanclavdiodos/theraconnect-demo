<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'appointment_cancelled' to the notifications.type enum so clinicians
     * get a push when a patient cancels their own appointment (the patient
     * already knows — they performed the action).
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
            "'appointment_rescheduled','appointment_reminder','appointment_cancelled',".
            "'assignment_created','assignment_deadline','message_received',".
            "'assessment_assigned','patient_request','patient_request_approved',".
            "'patient_request_denied','generic') NOT NULL"
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
            "'assignment_deadline','message_received','assessment_assigned',".
            "'patient_request','patient_request_approved','patient_request_denied',".
            "'generic') NOT NULL"
        );
    }
};
