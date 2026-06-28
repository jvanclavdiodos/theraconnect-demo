<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'appointment_requested' to the notifications.type enum so clinicians
     * can be notified of new booking requests.
     *
     * MySQL only: the enum is a real ENUM column that must be ALTERed in place
     * on existing databases. SQLite (tests) has no native ALTER-enum and gets
     * the value from the (edited) create migration on migrate:fresh, so this
     * is a no-op there.
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
            "'assignment_deadline','generic') NOT NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE notifications MODIFY COLUMN type ENUM('.
            "'appointment_approved','appointment_rejected','appointment_rescheduled',".
            "'appointment_reminder','assignment_created','assignment_deadline',".
            "'generic') NOT NULL"
        );
    }
};
