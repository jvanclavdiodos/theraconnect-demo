<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            // Original client filename, so downloads keep the real name/extension
            // (the stored file uses a hashed name with a guessed extension).
            $table->string('original_name', 255)->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn('original_name');
        });
    }
};
