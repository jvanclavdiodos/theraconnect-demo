<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('gender', 30)->nullable()->after('date_of_birth');
            $table->string('educational_attainment', 50)->nullable()->after('gender');
            $table->string('employment_status', 50)->nullable()->after('educational_attainment');
            $table->text('personal_issues')->nullable()->after('employment_status');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['gender', 'educational_attainment', 'employment_status', 'personal_issues']);
        });
    }
};
