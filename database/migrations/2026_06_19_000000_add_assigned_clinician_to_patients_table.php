<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives each patient an explicit owning clinician (their "caseload" owner).
 *
 * Before this, the only patient↔clinician link was transactional (via
 * appointments/assignments), so there was no way to scope a clinician to
 * "their" patients. This column is the anchor for clinician self-scoping:
 * a clinician sees/acts on patients where assigned_clinician_id matches; an
 * admin sees all. Nullable so a patient can be unassigned (admin-only to
 * assign), and nullOnDelete so removing a clinician doesn't delete patients.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('assigned_clinician_id')
                ->nullable()
                ->after('user_id')
                ->constrained('clinicians')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_clinician_id');
        });
    }
};
