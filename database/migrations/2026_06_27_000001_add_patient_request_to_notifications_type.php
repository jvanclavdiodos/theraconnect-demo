<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add the patient clinician-request notification types so a clinician is
     * notified of a new request, and the patient is notified when it is
     * approved or denied.
     *
     * MySQL only (real ENUM column needs an in-place ALTER on existing DBs).
     * SQLite (tests) gets the values from the edited create migration on
     * migrate:fresh, so this is a no-op there.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE notifications MODIFY COLUMN type ENUM(".
            "'appointment_requested','appointment_approved','appointment_rejected',".
            "'appointment_rescheduled','appointment_reminder','assignment_created',".
            "'assignment_deadline','message_received','assessment_assigned',".
            "'patient_request','patient_request_approved','patient_request_denied',".
            "'generic') NOT NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE notifications MODIFY COLUMN type ENUM(".
            "'appointment_requested','appointment_approved','appointment_rejected',".
            "'appointment_rescheduled','appointment_reminder','assignment_created',".
            "'assignment_deadline','message_received','assessment_assigned','generic') NOT NULL"
        );
    }
};
