<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'no_show' to the appointments.status enum so a clinician can record
     * that a patient missed a session (attendance / engagement tracking).
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
            "ALTER TABLE appointments MODIFY COLUMN status ENUM(".
            "'pending','approved','rejected','rescheduled','completed','cancelled','no_show'".
            ") NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE appointments MODIFY COLUMN status ENUM(".
            "'pending','approved','rejected','rescheduled','completed','cancelled'".
            ") NOT NULL DEFAULT 'pending'"
        );
    }
};
