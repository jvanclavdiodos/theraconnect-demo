<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a self-registering patient *request* a clinician at sign-up, subject to
 * that clinician's approval — instead of landing unassigned and invisible to
 * every clinician's caseload (patients tab + messages scope on
 * assigned_clinician_id).
 *
 * - requested_clinician_id: the clinician the patient asked for.
 * - clinician_request_status: pending → the clinician must approve/deny;
 *   approved → assigned_clinician_id is set (patient joins the caseload);
 *   denied → patient may choose another clinician. NULL means no request was
 *   ever made (e.g. admin/clinician-provisioned patients, or seeded records).
 *
 * nullOnDelete so removing a clinician doesn't delete the patient row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('requested_clinician_id')
                ->nullable()
                ->after('assigned_clinician_id')
                ->constrained('clinicians')
                ->nullOnDelete();

            $table->enum('clinician_request_status', ['pending', 'approved', 'denied'])
                ->nullable()
                ->after('requested_clinician_id');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_clinician_id');
            $table->dropColumn('clinician_request_status');
        });
    }
};
